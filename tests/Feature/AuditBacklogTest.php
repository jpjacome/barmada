<?php

namespace Tests\Feature;

use App\Livewire\AllOrdersList;
use App\Livewire\TablesList;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Final audit backlog: table archiving [#5], cancellable orders [#12],
 * the dead orders counter [#17] and business_name persistence [#20].
 */
class AuditBacklogTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_a_closed_table_with_history_can_be_archived_and_restored(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 3]);
        $this->makeOrderFor($editor, ['table_id' => $table->id]);

        $this->actingAs($editor);
        $component = Livewire::test(TablesList::class)->call('archiveTable', $table->id);

        $this->assertNotNull($table->fresh()->archived_at);
        // Hidden from the working grid, listed under archived.
        $this->assertFalse(collect($component->get('tables'))->contains('id', $table->id));
        $this->assertTrue(collect($component->get('archivedTables'))->contains('id', $table->id));

        // The QR flow no longer recognizes it.
        auth()->logout();
        $this->get('/qr-entry/'.rawurlencode($editor->username).'/3')->assertNotFound();

        // Restore brings it back into service.
        $this->actingAs($editor);
        Livewire::test(TablesList::class)->call('restoreTable', $table->id);
        $this->assertNull($table->fresh()->archived_at);
    }

    public function test_an_open_table_cannot_be_archived(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        $this->actingAs($editor);
        $component = Livewire::test(TablesList::class)->call('archiveTable', $table->id);

        $this->assertNull($table->fresh()->archived_at);
        $component->assertSet('showErrorModal', true);
    }

    public function test_cancelled_orders_drop_out_of_revenue_and_payment_totals(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['price' => 10.00]);

        $kept = $this->makeOrderFor($editor, ['table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending']);
        $this->addItem($kept, $product);
        $mistake = $this->makeOrderFor($editor, ['table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending']);
        $this->addItem($mistake, $product);

        $this->actingAs($editor);
        Livewire::test(AllOrdersList::class)->call('cancelOrder', $mistake->id);
        $this->assertSame('cancelled', $mistake->fresh()->status);

        // Payment modal totals only count the live order.
        $component = Livewire::test(TablesList::class)->call('viewTableOrders', $table->id);
        $this->assertEquals(10.00, $component->get('tableTotal'));

        // Revenue scope excludes it, and the cancel action is final.
        $this->assertSame(1, Order::countable()->count());
        Livewire::test(AllOrdersList::class)->call('toggleStatus', $mistake->id);
        $this->assertSame('cancelled', $mistake->fresh()->status);
    }

    public function test_the_meaningless_orders_counter_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('tables', 'orders'));
    }

    public function test_registration_persists_the_business_name_and_the_menu_shows_it(): void
    {
        $this->post('/register', [
            'username' => 'cantina9',
            'email' => 'owner@cantina.test',
            'business_name' => 'Cantina Nueve',
            'table_count' => 2,
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
        ]);

        $owner = \App\Models\User::where('username', 'cantina9')->sole();
        $this->assertSame('Cantina Nueve', $owner->business_name);

        // The venue name greets guests on the menu.
        $this->post('/logout');
        [$table, $guestSession] = $this->openTableWithSession($owner->refresh());
        $this->approveDevice($guestSession);
        $this->makeProductFor($owner);
        $this->get('/order/'.$table->unique_token)->assertOk()->assertSee('Cantina Nueve');
    }
}
