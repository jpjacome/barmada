<?php

namespace Tests\Feature;

use App\Livewire\ProductsList;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * order_items.product_id is a plain foreign key with no cascade, so
 * deleting a product that has ever been sold raised a raw database error
 * — a 500 on the API and an unhandled exception in Livewire. Sold
 * products are retired with the 86 toggle; the history has to survive.
 */
class ProductDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        return User::factory()->create([
            'is_editor' => true,
            'username' => 'bar'.uniqid(),
        ]);
    }

    private function soldProduct(User $editor): Product
    {
        $product = Product::create([
            'name' => 'Pilsener',
            'price' => 2.50,
            'editor_id' => $editor->id,
            'is_available' => true,
        ]);

        $table = Table::create([
            'editor_id' => $editor->id,
            'table_number' => 1,
            'status' => 'open',
        ]);

        $order = Order::create([
            'table_id' => $table->id,
            'status' => 'pending',
            'total_amount' => 2.50,
            'amount_paid' => 0,
            'amount_left' => 2.50,
            'editor_id' => $editor->id,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 2.50,
            'is_paid' => false,
            'item_index' => 0,
        ]);

        return $product;
    }

    public function test_api_refuses_to_delete_a_product_with_order_history(): void
    {
        $editor = $this->makeEditor();
        $product = $this->soldProduct($editor);

        Sanctum::actingAs($editor, $editor->apiTokenAbilities());

        $this->deleteJson('/api/v1/products/'.$product->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_livewire_refuses_to_delete_a_product_with_order_history(): void
    {
        $editor = $this->makeEditor();
        $product = $this->soldProduct($editor);

        $this->actingAs($editor);

        Livewire::test(ProductsList::class)->call('deleteProduct', $product->id);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_an_unsold_product_can_still_be_deleted(): void
    {
        $editor = $this->makeEditor();
        $product = Product::create([
            'name' => 'Never Ordered',
            'price' => 1.00,
            'editor_id' => $editor->id,
            'is_available' => true,
        ]);

        Sanctum::actingAs($editor, $editor->apiTokenAbilities());

        $this->deleteJson('/api/v1/products/'.$product->id)->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
