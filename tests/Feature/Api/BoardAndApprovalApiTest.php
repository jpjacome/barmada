<?php

namespace Tests\Feature\Api;

use App\Models\ServiceRequest;
use App\Models\TableSessionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BoardAndApprovalApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_board_returns_pending_orders_approvals_and_service_requests(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 2]);

        $pending = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending',
        ]);
        $this->addItem($pending, $beer);
        $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'delivered',
        ]);

        ServiceRequest::create([
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'editor_id' => $editor->id,
            'type' => ServiceRequest::TYPE_BILL,
            'status' => 'pending',
        ]);

        TableSessionRequest::create([
            'table_session_id' => $session->id,
            'ip_address' => '10.0.0.9',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->apiActingAs($editor);

        $response = $this->getJson('/api/v1/board');

        $response->assertOk();
        $this->assertCount(1, $response->json('pending_orders'));
        $this->assertSame($pending->id, $response->json('pending_orders.0.id'));
        $this->assertCount(1, $response->json('service_requests'));
        $this->assertSame('bill', $response->json('service_requests.0.type'));
        $this->assertCount(1, $response->json('approval_requests'));
        $this->assertSame('additional_guest', $response->json('approval_requests.0.scope'));
    }

    public function test_board_is_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA, ['status' => 'pending']);
        $orderB = $this->makeOrderFor($editorB, ['status' => 'pending']);

        $this->apiActingAs($editorA);
        $ids = collect($this->getJson('/api/v1/board')->json('pending_orders'))->pluck('id');

        $this->assertTrue($ids->contains($orderA->id));
        $this->assertFalse($ids->contains($orderB->id));
    }

    public function test_admin_board_spans_tenants(): void
    {
        $admin = $this->makeAdmin();
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->makeOrderFor($editorA, ['status' => 'pending']);
        $this->makeOrderFor($editorB, ['status' => 'pending']);

        $this->apiActingAs($admin);

        $this->assertCount(2, $this->getJson('/api/v1/board')->json('pending_orders'));
    }

    public function test_approving_a_pending_table_adopts_waiting_devices_first_guest_flow(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'pending_approval']);

        // Two devices scanned while the table was closed — no session yet.
        $first = TableSessionRequest::create([
            'table_id' => $table->id,
            'ip_address' => '10.0.0.1',
            'status' => 'pending',
            'requested_at' => now()->subMinute(),
        ]);
        $second = TableSessionRequest::create([
            'table_id' => $table->id,
            'ip_address' => '10.0.0.2',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->apiActingAs($editor);

        // The queue shows them as first-guest approvals.
        $queue = $this->getJson('/api/v1/approval-requests')->json('approval_requests');
        $this->assertCount(2, $queue);
        $this->assertSame('first_guest', $queue[0]['scope']);

        $response = $this->postJson("/api/v1/tables/{$table->id}/approve");
        $response->assertOk()->assertJsonPath('table.status', 'open');

        $sessionId = $response->json('session.id');
        $this->assertNotNull($sessionId);

        // First requester approved into the fresh session [F-1]; the
        // second stays pending but is adopted.
        $first->refresh();
        $second->refresh();
        $this->assertSame('approved', $first->status);
        $this->assertSame($sessionId, $first->table_session_id);
        $this->assertSame('pending', $second->status);
        $this->assertSame($sessionId, $second->table_session_id);
    }

    public function test_approving_an_additional_guest_request(): void
    {
        $editor = $this->makeEditor();
        [, $session] = $this->openTableWithSession($editor);

        $request = TableSessionRequest::create([
            'table_session_id' => $session->id,
            'ip_address' => '10.0.0.3',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/approval-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('approval_request.status', 'approved');

        $this->assertNotNull($request->refresh()->approved_at);
    }

    public function test_approving_a_foreign_tenants_request_is_invisible(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        [, $sessionB] = $this->openTableWithSession($editorB);

        $request = TableSessionRequest::create([
            'table_session_id' => $sessionB->id,
            'ip_address' => '10.0.0.4',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->apiActingAs($editorA);

        $this->postJson("/api/v1/approval-requests/{$request->id}/approve")->assertStatus(404);
        $this->assertSame('pending', $request->refresh()->status);
    }

    public function test_approving_an_already_open_table_is_rejected(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        $this->apiActingAs($editor);

        $this->postJson("/api/v1/tables/{$table->id}/approve")->assertStatus(422);
    }
}
