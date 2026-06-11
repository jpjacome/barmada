<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * HTTP endpoint authorization regressions [C-1, H-1, H-2].
 *
 * - The unauthenticated admin password-reset backdoor stays removed.
 * - The legacy order endpoints (world-readable order data, the AJAX
 *   update writing dropped columns, the duplicate orders page) were
 *   REMOVED in the P1 cleanup; these tests pin that they stay gone.
 * - The legacy analytics JSON endpoints (which once honored a
 *   client-supplied editor_id [H-2]) were removed with them; analytics
 *   aggregation now lives only in the editor-gated dashboard component.
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

    public function test_legacy_order_endpoints_stay_removed(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        // [H-1 → removed] The world-readable order-data endpoint and the
        // AJAX/status update endpoints are gone for guests AND owners.
        $this->get('/api/orders/'.$order->id)->assertNotFound();
        $this->actingAs($editor)->get('/api/orders/'.$order->id)->assertNotFound();

        $this->put('/orders/'.$order->id, ['status' => 'delivered'])->assertNotFound();
        $this->patch('/orders/'.$order->id, ['status' => 'delivered'])->assertNotFound();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_legacy_orders_page_stays_removed(): void
    {
        $editor = $this->makeEditor();

        // The duplicate /orders page was consolidated into /all-orders.
        $this->actingAs($editor)->get('/orders')->assertNotFound();
        $this->actingAs($editor)->get('/all-orders')->assertOk();
    }

    public function test_legacy_analytics_endpoints_stay_removed(): void
    {
        $editor = $this->makeEditor();

        // [H-2 → removed] These JSON endpoints once exposed any tenant's
        // revenue via a client-supplied editor_id. They no longer exist.
        foreach (['/analytics/sales-stats', '/analytics/product-category-stats', '/analytics/service-ops-stats'] as $uri) {
            $this->get($uri)->assertNotFound();
            $this->actingAs($editor)->get($uri)->assertNotFound();
        }
    }

    public function test_legacy_numbers_endpoints_stay_removed(): void
    {
        // The deprecated demo feature and its unauthenticated read
        // endpoints are gone.
        $this->get('/api-numbers')->assertNotFound();
        $this->get('/numbers')->assertNotFound();
        $this->get('/numbers/livewire')->assertNotFound();
    }

    public function test_tables_resource_leftovers_stay_removed(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor);

        // Only the index remains; table actions live in the Livewire
        // component. The broken create/show/edit pages are gone.
        $this->actingAs($editor)->get('/tables')->assertOk();
        $this->actingAs($editor)->get('/tables/create')->assertNotFound();
        $this->actingAs($editor)->get('/tables/'.$table->id.'/edit')->assertNotFound();
        $this->actingAs($editor)->put('/tables/'.$table->id, [])->assertNotFound();
        $this->actingAs($editor)->delete('/tables/'.$table->id)->assertNotFound();
    }

    public function test_tenant_deletion_is_admin_only(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();

        // Editors cannot delete establishments — not even their own. The
        // admin middleware bounces non-admins back to their dashboard.
        $this->actingAs($editorA)
            ->delete('/admin/editors/'.$editorB->id)
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($editorB->fresh());
    }
}
