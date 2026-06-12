<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OrderLifecycleApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    public function test_manual_order_creates_per_unit_items_with_note_and_creator(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $beer = $this->makeProductFor($editor, ['price' => 2.50]);
        $nachos = $this->makeProductFor($editor, ['price' => 3.00]);

        $this->apiActingAs($editor);

        $response = $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'products' => [$beer->id => 2, $nachos->id => 1],
            'note' => 'sin hielo',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.note', 'sin hielo')
            ->assertJsonPath('data.created_by', $editor->id)
            ->assertJsonPath('data.table_session_id', $session->id)
            ->assertJsonPath('data.total', 8)
            ->assertJsonCount(3, 'data.items');

        $order = Order::find($response->json('data.id'));
        $this->assertSame(3, $order->items()->count());
        $this->assertSame([0, 1, 2], $order->items()->orderBy('item_index')->pluck('item_index')->all());
        $this->assertTrue($order->items->every(fn ($item) => $item->quantity === 1));
    }

    public function test_order_with_sold_out_product_is_rejected_and_nothing_persists(): void
    {
        $editor = $this->makeEditor();
        [$table] = $this->openTableWithSession($editor);
        $soldOut = $this->makeProductFor($editor, ['is_available' => false]);

        $this->apiActingAs($editor);

        $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'products' => [$soldOut->id => 1],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_order_with_foreign_tenant_product_is_rejected(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        [$table] = $this->openTableWithSession($editorA);
        $foreignProduct = $this->makeProductFor($editorB);

        $this->apiActingAs($editorA);

        $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'products' => [$foreignProduct->id => 1],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_order_on_table_without_open_session_is_rejected(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTableFor($editor, ['status' => 'closed']);
        $beer = $this->makeProductFor($editor);

        $this->apiActingAs($editor);

        $this->postJson('/api/v1/orders', [
            'table_id' => $table->id,
            'products' => [$beer->id => 1],
        ])->assertStatus(422);
    }

    public function test_order_can_be_delivered_and_reopened(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor, ['status' => 'pending']);

        $this->apiActingAs($editor);

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_cancellation_rules_match_the_board(): void
    {
        $editor = $this->makeEditor();
        $this->apiActingAs($editor);

        // Pending orders cancel fine…
        $pending = $this->makeOrderFor($editor, ['status' => 'pending']);
        $this->patchJson("/api/v1/orders/{$pending->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // …cancelled is final…
        $this->patchJson("/api/v1/orders/{$pending->id}/status", ['status' => 'pending'])
            ->assertStatus(422);

        // …and delivered orders (served product) cannot be cancelled.
        $delivered = $this->makeOrderFor($editor, ['status' => 'delivered']);
        $this->patchJson("/api/v1/orders/{$delivered->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422);
    }

    public function test_staff_can_run_the_board(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);
        $order = $this->makeOrderFor($editor, ['status' => 'pending']);

        $this->apiActingAs($staff);

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk();
    }

    public function test_index_filters_by_status(): void
    {
        $editor = $this->makeEditor();
        $this->makeOrderFor($editor, ['status' => 'pending']);
        $this->makeOrderFor($editor, ['status' => 'delivered']);

        $this->apiActingAs($editor);

        $response = $this->getJson('/api/v1/orders?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('pending', $response->json('data.0.status'));
    }

    public function test_order_can_be_deleted(): void
    {
        $editor = $this->makeEditor();
        $order = $this->makeOrderFor($editor);

        $this->apiActingAs($editor);

        $this->deleteJson("/api/v1/orders/{$order->id}")->assertOk();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
