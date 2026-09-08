<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The data-repair migration has to expand rows that the old edit-order
 * modal collapsed, and leave a healthy database untouched.
 */
class RepairCollapsedOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_000001_repair_collapsed_order_items.php');
    }

    private function seedOrder(int $quantity, bool $paid = false): Order
    {
        $editor = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);

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
            // Deliberately wrong, exactly as the bug left it: one row's
            // worth of money against three units of drink.
            'total_amount' => 2.50,
            'amount_paid' => 0,
            'amount_left' => 2.50,
            'editor_id' => $editor->id,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => 2.50,
            'is_paid' => $paid,
            'item_index' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }

    public function test_it_expands_a_collapsed_row_into_one_row_per_unit(): void
    {
        $order = $this->seedOrder(3);

        $this->migration()->up();

        $items = DB::table('order_items')->where('order_id', $order->id)->get();

        $this->assertCount(3, $items);
        $this->assertTrue($items->every(fn ($item) => (int) $item->quantity === 1));
        $this->assertSame([0, 1, 2], $items->pluck('item_index')->sort()->values()->all());
    }

    public function test_it_rewrites_the_orders_money_columns(): void
    {
        $order = $this->seedOrder(3);

        $this->migration()->up();

        $order->refresh();

        $this->assertEqualsWithDelta(7.50, (float) $order->total_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->amount_paid, 0.001);
        $this->assertEqualsWithDelta(7.50, (float) $order->amount_left, 0.001);
    }

    public function test_it_preserves_paid_state_across_expanded_units(): void
    {
        $order = $this->seedOrder(2, paid: true);

        $this->migration()->up();

        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        $order->refresh();

        $this->assertCount(2, $items);
        $this->assertTrue($items->every(fn ($item) => (bool) $item->is_paid));
        $this->assertEqualsWithDelta(5.00, (float) $order->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->amount_left, 0.001);
    }

    public function test_it_is_idempotent(): void
    {
        $order = $this->seedOrder(3);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(3, DB::table('order_items')->where('order_id', $order->id)->count());
    }
}
