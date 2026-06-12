<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The API twins of the web tenant-isolation suite: every cross-tenant id
 * must be INVISIBLE (404 via EditorScope-bounded binding), never a 403
 * that confirms existence — and the admin bypass must keep working.
 */
class ApiTenantIsolationTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_every_mutating_endpoint_404s_across_tenants(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        [$tableA, $sessionA] = $this->openTableWithSession($editorA);
        $productA = $this->makeProductFor($editorA);
        $orderA = $this->makeOrderFor($editorA, [
            'table_id' => $tableA->id,
            'table_session_id' => $sessionA->id,
            'status' => 'pending',
        ]);
        $this->addItem($orderA, $productA);

        $this->apiActingAs($editorB);

        $attempts = [
            ['get', "/api/v1/orders/{$orderA->id}", []],
            ['patch', "/api/v1/orders/{$orderA->id}/status", ['status' => 'delivered']],
            ['delete', "/api/v1/orders/{$orderA->id}", []],
            ['post', "/api/v1/orders/{$orderA->id}/items/toggle-paid", ['product_id' => $productA->id, 'item_index' => 0]],
            ['post', "/api/v1/orders/{$orderA->id}/settle", []],
            ['get', "/api/v1/tables/{$tableA->id}/session", []],
            ['post', "/api/v1/tables/{$tableA->id}/open", []],
            ['post', "/api/v1/tables/{$tableA->id}/close", []],
            ['post', "/api/v1/tables/{$tableA->id}/approve", []],
            ['post', "/api/v1/tables/{$tableA->id}/settle", []],
            ['post', "/api/v1/tables/{$tableA->id}/archive", []],
            ['post', "/api/v1/tables/{$tableA->id}/restore", []],
            ['post', "/api/v1/tables/{$tableA->id}/invoice", ['name' => 'X', 'tax_id' => 'Y']],
            ['post', "/api/v1/products/{$productA->id}/toggle-availability", []],
        ];

        foreach ($attempts as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload);
            $this->assertSame(
                404,
                $response->status(),
                "Expected 404 for {$method} {$uri}, got {$response->status()}."
            );
        }

        // Nothing changed under tenant A.
        $this->assertSame('pending', $orderA->refresh()->status);
        $this->assertSame('open', $tableA->refresh()->status);
        $this->assertTrue((bool) $productA->refresh()->is_available);
    }

    public function test_list_endpoints_never_leak_foreign_rows(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA, ['status' => 'pending']);
        $tableA = $orderA->table;
        $productA = $this->makeProductFor($editorA);

        $this->apiActingAs($editorB);

        $this->assertCount(0, $this->getJson('/api/v1/orders')->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/tables')->json('tables'));
        $this->assertCount(0, $this->getJson('/api/v1/products')->json('products'));
        $this->assertCount(0, $this->getJson('/api/v1/board')->json('pending_orders'));
    }

    public function test_staff_act_within_their_tenant_only(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $staffA = $this->makeStaff($editorA);

        $orderA = $this->makeOrderFor($editorA, ['status' => 'pending']);
        $orderB = $this->makeOrderFor($editorB, ['status' => 'pending']);

        $this->apiActingAs($staffA);

        $this->patchJson("/api/v1/orders/{$orderA->id}/status", ['status' => 'delivered'])->assertOk();
        $this->patchJson("/api/v1/orders/{$orderB->id}/status", ['status' => 'delivered'])->assertStatus(404);
        $this->assertSame('pending', $orderB->refresh()->status);
    }

    public function test_tenantless_users_see_nothing_and_touch_nothing(): void
    {
        $editorA = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA, ['status' => 'pending']);
        $nobody = $this->makeTenantlessUser();

        $this->apiActingAs($nobody);

        $this->assertCount(0, $this->getJson('/api/v1/board')->json('pending_orders'));
        $this->assertCount(0, $this->getJson('/api/v1/orders')->json('data'));
        $this->patchJson("/api/v1/orders/{$orderA->id}/status", ['status' => 'delivered'])->assertStatus(404);
    }

    public function test_admin_operates_across_tenants(): void
    {
        $admin = $this->makeAdmin();
        $editorA = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA, ['status' => 'pending']);

        $this->apiActingAs($admin);

        $this->getJson("/api/v1/orders/{$orderA->id}")->assertOk();
        $this->patchJson("/api/v1/orders/{$orderA->id}/status", ['status' => 'delivered'])->assertOk();
        $this->assertSame('delivered', $orderA->refresh()->status);
    }
}
