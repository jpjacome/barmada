<?php

namespace Tests\Feature;

use App\Livewire\AllOrdersList;
use App\Models\TableSessionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The first customer of a table session must get in with ONE staff
 * approval and WITHOUT rescanning the QR code. [F-1]
 *
 * Previously: scanning a closed table recorded nothing, the staff
 * "Approve" opened the table but approved nobody (its request query was
 * unsatisfiable), and the guest's status poll waited forever.
 */
class FirstCustomerFlowTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_scanning_a_closed_table_records_the_device(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 4]);

        $this->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();

        $this->assertSame('pending_approval', $table->fresh()->status);
        $request = TableSessionRequest::sole();
        $this->assertNull($request->table_session_id);
        $this->assertSame($table->id, $request->table_id);
        $this->assertSame('pending', $request->status);

        // Rescanning while still closed does not pile up duplicates.
        $this->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();
        $this->assertSame(1, TableSessionRequest::count());
    }

    public function test_staff_approval_opens_the_table_and_approves_the_waiting_guest(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 4]);

        // Guest scans the printed QR at the closed table.
        $this->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();

        // Staff tap the single Approve button on the orders board.
        $this->actingAs($editor);
        Livewire::test(AllOrdersList::class)->call('approveTableAndFirstClient', $table->id);
        $this->post(route('logout'));

        $table->refresh();
        $this->assertSame('open', $table->status);
        $this->assertNotNull($table->unique_token);

        $session = $table->sessions()->where('status', 'open')->latest('opened_at')->first();
        $this->assertNotNull($session);

        // The scanning device was adopted into the session AND approved.
        $request = TableSessionRequest::sole();
        $this->assertSame($session->id, $request->table_session_id);
        $this->assertSame('approved', $request->status);

        // The guest's waiting page poll now tells them to go in…
        $this->getJson('/poll-table-status/'.$table->id)
            ->assertOk()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('redirect_url', route('order.redirect', ['unique_token' => $table->unique_token]));

        // …and the order form actually opens for them.
        $this->get('/order/'.$table->unique_token)->assertOk();
    }

    public function test_only_the_first_requester_is_auto_approved(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 4]);

        // Two different devices scan while the table is closed.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();

        $this->actingAs($editor);
        Livewire::test(AllOrdersList::class)->call('approveTableAndFirstClient', $table->id);
        $this->post(route('logout'));

        $session = $table->fresh()->sessions()->where('status', 'open')->latest('opened_at')->first();
        $this->assertNotNull($session);

        // First device approved, second attached but pending staff approval.
        $first = TableSessionRequest::where('ip_address', '10.0.0.1')->sole();
        $second = TableSessionRequest::where('ip_address', '10.0.0.2')->sole();
        $this->assertSame('approved', $first->status);
        $this->assertSame($session->id, $first->table_session_id);
        $this->assertSame('pending', $second->status);
        $this->assertSame($session->id, $second->table_session_id);

        // The second device keeps waiting until individually approved.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->getJson('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'waiting_ip_approval');

        $this->actingAs($editor);
        Livewire::test(AllOrdersList::class)->call('approveClientRequest', $second->id);
        $this->post(route('logout'));

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->getJson('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'open');
    }

    public function test_polling_an_open_table_self_registers_the_device(): void
    {
        // Covers the path where staff open the table directly (tables page
        // toggle) instead of using the board's Approve button: the waiting
        // guest must still become visible to staff without rescanning.
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);

        $this->assertSame(0, TableSessionRequest::count());

        $this->getJson('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'waiting_ip_approval');

        $request = TableSessionRequest::sole();
        $this->assertSame($session->id, $request->table_session_id);
        $this->assertSame('pending', $request->status);

        // Polling again does not duplicate the request.
        $this->getJson('/poll-table-status/'.$table->id);
        $this->assertSame(1, TableSessionRequest::count());
    }

    public function test_orphan_requests_are_adopted_when_polling_an_open_table(): void
    {
        // Guest scanned while closed (orphan request), staff opened the
        // table WITHOUT the Approve button. The guest's poll must attach
        // their request to the new session so staff can approve them.
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 6]);

        $this->get('/qr-entry/'.rawurlencode($editor->username).'/6')->assertOk();
        $orphan = TableSessionRequest::sole();
        $this->assertNull($orphan->table_session_id);

        // Staff open the table from the tables screen (model hook makes the session).
        $table->refresh();
        $table->status = 'open';
        $table->save();

        $this->getJson('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'waiting_ip_approval');

        $session = $table->fresh()->sessions()->where('status', 'open')->latest('opened_at')->first();
        $this->assertNotNull($session);
        $this->assertSame($session->id, $orphan->fresh()->table_session_id);
        $this->assertSame(1, TableSessionRequest::count());
    }
}
