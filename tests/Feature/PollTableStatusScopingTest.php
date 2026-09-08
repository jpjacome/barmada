<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /poll-table-status/{table} runs without authentication, so EditorScope
 * does not apply to it and the lookup resolves any table in the database.
 * It also writes — it can create approval requests and fire push
 * notifications — so an ungated version let anyone enumerate every
 * venue's tables and spam arbitrary venues' staff devices.
 */
class PollTableStatusScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(string $username): User
    {
        return User::factory()->create([
            'is_editor' => true,
            'username' => $username,
        ]);
    }

    private function openTable(User $editor, int $number): Table
    {
        $token = (string) \Illuminate\Support\Str::uuid();

        $table = Table::create([
            'editor_id' => $editor->id,
            'table_number' => $number,
            'status' => 'open',
        ]);

        // unique_token is deliberately not mass-assignable.
        $table->unique_token = $token;
        $table->saveQuietly();

        TableSession::create([
            'table_id' => $table->id,
            'session_number' => 1,
            'date' => now()->toDateString(),
            'unique_token' => $token,
            'status' => 'open',
            'opened_at' => now(),
            'opened_by' => $editor->id,
            'editor_id' => $editor->id,
        ]);

        return $table;
    }

    public function test_a_stranger_cannot_read_another_venues_table_status(): void
    {
        $editor = $this->makeEditor('barone');
        $table = $this->openTable($editor, 1);

        $this->get('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'not_found');
    }

    public function test_a_stranger_cannot_create_approval_requests_or_trigger_push(): void
    {
        $editor = $this->makeEditor('bartwo');
        $table = $this->openTable($editor, 2);

        $this->get('/poll-table-status/'.$table->id);

        $this->assertSame(
            0,
            TableSessionRequest::count(),
            'Polling a table you never scanned must not register a device.'
        );
    }

    public function test_an_editor_cannot_poll_another_tenants_table(): void
    {
        $mine = $this->makeEditor('mine');
        $theirs = $this->makeEditor('theirs');
        $table = $this->openTable($theirs, 3);

        $this->actingAs($mine)
            ->get('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'not_found');
    }

    public function test_a_guest_who_scanned_may_still_poll(): void
    {
        $editor = $this->makeEditor('barfour');
        $table = $this->openTable($editor, 4);

        // The scan is what earns access.
        $this->get('/qr-entry/'.rawurlencode($editor->username).'/4')->assertOk();

        $this->get('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'waiting_ip_approval');
    }

    public function test_a_returning_device_may_poll_without_rescanning(): void
    {
        $editor = $this->makeEditor('barfive');
        $table = $this->openTable($editor, 5);
        $device = str_repeat('a', 40);

        // A request row from an earlier visit, with no session marker:
        // a returning phone must not be locked out.
        TableSessionRequest::create([
            'table_id' => $table->id,
            'ip_address' => '127.0.0.1',
            'device_token' => $device,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->withUnencryptedCookie(DeviceToken::COOKIE, $device)
            ->get('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'waiting_ip_approval');
    }

    public function test_venue_staff_may_poll_their_own_table(): void
    {
        $editor = $this->makeEditor('barsix');
        $table = $this->openTable($editor, 6);

        $this->actingAs($editor)
            ->get('/poll-table-status/'.$table->id)
            ->assertJsonPath('status', 'open');
    }
}
