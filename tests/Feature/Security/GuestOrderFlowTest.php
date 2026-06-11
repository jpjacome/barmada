<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\TableSessionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Guest QR order flow hardening [H-4, H-6, M-4].
 *
 * Guests reach the order form through a per-table QR token. The flow is
 * gated by device (IP) approval on the table's open session, products must
 * belong to the table's tenant, quantities are bounded, and the public
 * endpoints are rate limited.
 */
class GuestOrderFlowTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_unapproved_device_cannot_open_the_order_form(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        // [H-4] The form itself is gated, not just the waiting page.
        $this->get('/order/'.$table->unique_token)->assertForbidden();
    }

    public function test_unapproved_device_cannot_submit_an_order(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor);

        $this->post('/order/'.$table->unique_token, [
            'products' => [$product->id => 1],
        ])->assertForbidden();

        $this->assertSame(0, Order::acrossEditors()->count());
    }

    public function test_approved_device_can_submit_an_order(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor, ['price' => 5.00]);
        $this->approveDevice($session);

        $this->post('/order/'.$table->unique_token, [
            'products' => [$product->id => 2],
        ])->assertRedirect(route('orders.confirmation', absolute: false));

        $order = Order::acrossEditors()->firstOrFail();
        // The order is recorded against the TABLE's tenant.
        $this->assertSame($editor->id, $order->editor_id);
        $this->assertSame($table->id, $order->table_id);
        $this->assertSame($session->id, $order->table_session_id);
        $this->assertSame(2, $order->items()->count());
        $this->assertEquals(10.00, (float) $order->total_amount);
    }

    public function test_closed_tables_reject_guest_traffic(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $token = $table->unique_token;
        $table->forceFill(['status' => 'closed'])->saveQuietly();

        $this->get('/order/'.$token)->assertForbidden();
        $this->post('/order/'.$token, ['products' => [1 => 1]])->assertForbidden();
    }

    public function test_authenticated_tenant_members_bypass_device_approval(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);

        // No approved device request exists, but the tenant's own editor
        // and staff (and the admin) may use the form directly.
        $this->actingAs($editor)->get('/order/'.$table->unique_token)->assertOk();
        $this->actingAs($this->makeStaff($editor))->get('/order/'.$table->unique_token)->assertOk();
        $this->actingAs($this->makeAdmin())->get('/order/'.$table->unique_token)->assertOk();
    }

    public function test_other_tenants_users_get_no_approval_bypass(): void
    {
        $editorA = $this->makeEditor();
        [$table] = $this->openTableWithSession($editorA);

        // Being logged in somewhere else grants nothing here.
        $this->actingAs($this->makeEditor())
            ->get('/order/'.$table->unique_token)
            ->assertForbidden();
    }

    public function test_cross_tenant_products_are_rejected_on_guest_submission(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editorA);
        $foreignProduct = $this->makeProductFor($editorB);
        $this->approveDevice($session);

        // [H-6] Product ids must resolve within the table's tenant; the
        // order is rejected before any row is persisted.
        $this->post('/order/'.$table->unique_token, [
            'products' => [$foreignProduct->id => 1],
        ])->assertStatus(422);

        $this->assertSame(0, Order::acrossEditors()->count());
    }

    public function test_guest_quantities_and_product_counts_are_capped(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor);
        $this->approveDevice($session);

        // [M-4] Max 99 units per product…
        $this->postJson('/order/'.$table->unique_token, [
            'products' => [$product->id => 100],
        ])->assertStatus(422);

        // …no zero/absent quantities…
        $this->postJson('/order/'.$table->unique_token, [
            'products' => [$product->id => 0],
        ])->assertStatus(422);

        // …and max 50 distinct products per order.
        $oversized = [];
        for ($i = 1; $i <= 51; $i++) {
            $oversized[$i] = 1;
        }
        $this->postJson('/order/'.$table->unique_token, [
            'products' => $oversized,
        ])->assertStatus(422);

        $this->assertSame(0, Order::acrossEditors()->count());
    }

    public function test_authenticated_order_creation_is_capped_too(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $product = $this->makeProductFor($editor);

        // [M-4] The same bounds apply to the editor/staff entry path.
        $this->actingAs($editor)->postJson('/order', [
            'table_id' => $table->id,
            'products' => [$product->id => 100],
        ])->assertStatus(422);
    }

    public function test_guest_endpoints_are_rate_limited(): void
    {
        // [H-4/M-4] Throttle middleware stays attached to the public flows.
        $routes = Route::getRoutes();

        $this->assertContains('throttle:60,1', $routes->getByName('order.guest.store')->gatherMiddleware());
        $this->assertContains('ip.approved', $routes->getByName('order.guest.store')->gatherMiddleware());
        $this->assertContains('throttle:30,1', $routes->getByName('orders.qr-entry')->gatherMiddleware());
        $this->assertContains('ip.approved', $routes->getByName('order.redirect')->gatherMiddleware());

        $poll = collect($routes->getRoutes())
            ->first(fn ($route) => $route->uri() === 'poll-table-status/{table}');
        $this->assertNotNull($poll);
        $this->assertContains('throttle:120,1', $poll->gatherMiddleware());
    }

    public function test_qr_entry_records_a_single_pending_request_per_device(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor, ['table_number' => 7]);

        $uri = '/qr-entry/'.rawurlencode($editor->username).'/7';

        $this->get($uri)->assertOk();
        $this->assertSame(1, $session->sessionRequests()->where('status', 'pending')->count());

        // Re-scanning does not pile up duplicate requests.
        $this->get($uri)->assertOk();
        $this->assertSame(1, $session->sessionRequests()->count());
    }

    public function test_qr_entry_redirects_approved_devices_to_the_order_form(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor, ['table_number' => 3]);
        $this->approveDevice($session);

        $this->get('/qr-entry/'.rawurlencode($editor->username).'/3')
            ->assertRedirect(route('order.redirect', ['unique_token' => $table->unique_token], absolute: false));
    }

    public function test_qr_entry_on_a_closed_table_only_flags_it_pending(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed', 'table_number' => 9]);

        $this->get('/qr-entry/'.rawurlencode($editor->username).'/9')->assertOk();

        $this->assertSame('pending_approval', $table->fresh()->status);
        $this->assertSame(0, TableSessionRequest::count());
    }
}
