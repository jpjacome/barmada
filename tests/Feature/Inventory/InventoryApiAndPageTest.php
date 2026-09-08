<?php

namespace Tests\Feature\Inventory;

use App\Livewire\InventoryPanel;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryApiAndPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_manages_items_movements_and_recipes_within_the_tenant(): void
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $rival = User::factory()->create(['is_editor' => true, 'username' => 'rival'.uniqid()]);
        $rivalItem = StockItem::create(['editor_id' => $rival->id, 'name' => 'Rival Rum', 'unit' => 'ml']);
        $product = Product::create(['name' => 'Cuba Libre', 'price' => 6, 'editor_id' => $venue->id, 'is_available' => true]);

        Sanctum::actingAs($venue, $venue->apiTokenAbilities());

        $created = $this->postJson('/api/v1/stock-items', ['name' => 'Ron', 'unit' => 'ml', 'reorder_level' => 200])
            ->assertCreated()->assertJsonPath('stock_item.quantity_on_hand', 0);
        $id = $created->json('stock_item.id');

        $this->postJson("/api/v1/stock-items/{$id}/movements", ['type' => 'purchase', 'quantity' => 750, 'unit_cost' => 0.02])
            ->assertCreated()->assertJsonPath('stock_item.quantity_on_hand', 750)->assertJsonPath('stock_item.unit_cost', 0.02);

        $this->postJson("/api/v1/stock-items/{$id}/movements", ['type' => 'count', 'quantity' => 700])
            ->assertCreated()->assertJsonPath('movement.quantity', -50);

        $this->getJson("/api/v1/stock-items/{$id}/movements")->assertOk()->assertJsonCount(2, 'movements');
        $this->getJson('/api/v1/stock-items')->assertOk()->assertJsonCount(1, 'stock_items')->assertJsonPath('stock_items.0.is_low', false);

        $this->putJson("/api/v1/products/{$product->id}/components", ['components' => [['stock_item_id' => $id, 'quantity_per_unit' => 45]]])
            ->assertOk()->assertJsonPath('components.0.quantity_per_unit', 45);

        // Cross-tenant: the rival's item is invisible and unusable.
        $this->postJson("/api/v1/stock-items/{$rivalItem->id}/movements", ['type' => 'waste', 'quantity' => 1])->assertNotFound();
        $this->putJson("/api/v1/products/{$product->id}/components", ['components' => [['stock_item_id' => $rivalItem->id, 'quantity_per_unit' => 1]]])->assertStatus(422);
    }

    public function test_staff_can_receive_and_count_but_not_retire_or_edit_recipes(): void
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $staff = User::factory()->create(['username' => 'mesero'.uniqid()]);
        $staff->forceFill(['is_staff' => true, 'editor_id' => $venue->id])->save();
        $item = StockItem::create(['editor_id' => $venue->id, 'name' => 'Limones', 'unit' => 'unit']);

        Sanctum::actingAs($staff, $staff->apiTokenAbilities());

        $this->postJson("/api/v1/stock-items/{$item->id}/movements", ['type' => 'purchase', 'quantity' => 50, 'unit_cost' => 0.1])->assertCreated();
        $this->patchJson("/api/v1/stock-items/{$item->id}", ['is_active' => false])->assertForbidden();
    }

    public function test_inventory_page_renders_for_editor_and_staff_and_flags_low_stock(): void
    {
        $venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $staff = User::factory()->create(['username' => 'mesero'.uniqid()]);
        $staff->forceFill(['is_staff' => true, 'editor_id' => $venue->id])->save();
        StockItem::create(['editor_id' => $venue->id, 'name' => 'Pilsener botella', 'unit' => 'unit', 'quantity_on_hand' => 3, 'reorder_level' => 12]);

        $this->actingAs($venue)->get('/inventory')->assertOk()->assertSee('Pilsener botella')->assertSee('Low stock');
        $this->actingAs($staff)->get('/inventory')->assertOk()->assertSee('Pilsener botella');

        $this->actingAs($venue);
        Livewire::test(InventoryPanel::class)
            ->set('name', 'Gin')->set('unit', 'ml')->set('reorderLevel', 500)
            ->call('addItem')
            ->assertSee('Gin');

        $this->assertDatabaseHas('stock_items', ['name' => 'Gin', 'editor_id' => $venue->id, 'unit' => 'ml']);
    }
}
