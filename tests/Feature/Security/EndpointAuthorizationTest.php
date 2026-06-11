<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * HTTP endpoint authorization regressions [C-1, H-1, H-2].
 *
 * - The unauthenticated admin password-reset backdoor stays removed.
 * - Order data/update endpoints require authentication and ownership.
 * - Analytics endpoints derive the tenant from the authenticated user and
 *   never honor a client-supplied editor_id.
 */
class EndpointAuthorizationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_the_admin_reset_backdoor_route_stays_removed(): void
    {
        // [C-1] /reset-admin reset the admin password to a known value
        // without authentication. It must never come back.
        $this->get('/reset-admin')->assertNotFound();
        $this->post('/reset-admin')->assertNotFound();
    }

    public function test_order_data_endpoint_requires_authentication(): void
    {
        $order = $this->makeOrderFor($this->makeEditor());

        // [H-1] Previously world-readable: leaked order + tenant data.
        $this->get('/api/orders/'.$order->id)->assertRedirect('/login');
    }

    public function test_order_data_endpoint_is_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $orderB = $this->makeOrderFor($this->makeEditor());

        $this->actingAs($editorA)
            ->get('/api/orders/'.$orderB->id)
            ->assertNotFound();
    }

    public function test_staff_can_read_their_own_tenants_orders(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        $this->actingAs($this->makeStaff($editor))
            ->get('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    public function test_ajax_order_update_requires_authentication(): void
    {
        $order = $this->makeOrderFor($this->makeEditor());

        // [H-1] PUT /orders/{order} was callable without authentication.
        $this->put('/orders/'.$order->id, ['status' => 'completed'])
            ->assertRedirect('/login');

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_ajax_order_update_is_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $orderB = $this->makeOrderFor($this->makeEditor());

        $this->actingAs($editorA)
            ->put('/orders/'.$orderB->id, ['status' => 'completed'])
            ->assertNotFound();

        $this->assertSame('pending', $orderB->fresh()->status);
    }

    public function test_owner_can_update_order_status_via_ajax_endpoint(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        $this->actingAs($editor)
            ->put('/orders/'.$order->id, ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_ajax_order_update_rejects_other_tenants_tables(): void
    {
        $editorA = $this->makeEditor();
        $order = $this->makeOrderFor($editorA);
        $foreignTable = $this->makeTableFor($this->makeEditor());

        // [batch 4] The target table must resolve within the caller's
        // tenant; a foreign table id 404s through the scope.
        $this->actingAs($editorA)
            ->put('/orders/'.$order->id, [
                'table_id' => $foreignTable->id,
                'products' => [1 => 1],
            ])
            ->assertNotFound();

        $this->assertNotSame($foreignTable->id, $order->fresh()->table_id);
    }

    public function test_order_status_update_form_endpoint_is_policy_gated(): void
    {
        $editorA = $this->makeEditor();
        $orderA = $this->makeOrderFor($editorA);
        $orderB = $this->makeOrderFor($this->makeEditor());

        // Same tenant: allowed (staff act for their editor).
        $this->actingAs($this->makeStaff($editorA))
            ->patch('/orders/'.$orderA->id, ['status' => 'completed'])
            ->assertRedirect(route('orders.index', absolute: false));
        $this->assertSame('completed', $orderA->fresh()->status);

        // Cross tenant: invisible.
        $this->actingAs($editorA)
            ->patch('/orders/'.$orderB->id, ['status' => 'completed'])
            ->assertNotFound();
    }

    public function test_analytics_requires_an_authenticated_editor(): void
    {
        $this->get('/analytics/sales-stats')->assertRedirect('/login');

        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        // The analytics dashboard is editor-only.
        $this->actingAs($staff)->get('/analytics/sales-stats')->assertForbidden();
    }

    public function test_analytics_ignores_client_supplied_editor_id(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        $tableA = $this->makeTableFor($editorA);
        $this->makeOrderFor($editorA, ['table_id' => $tableA->id, 'total_amount' => 100]);
        $this->makeOrderFor($editorA, ['table_id' => $tableA->id, 'total_amount' => 50]);
        $this->makeOrderFor($editorB, ['total_amount' => 999]);

        // [H-2] editor_id used to be honored from request input, exposing
        // any tenant's revenue. The tenant now always derives from the
        // authenticated user.
        $response = $this->actingAs($editorA)
            ->get('/analytics/sales-stats?range=today&editor_id='.$editorB->id)
            ->assertOk();

        $this->assertEquals(150, $response->json('data.total_sales'));
        $this->assertSame(2, $response->json('data.order_count'));
    }
}
