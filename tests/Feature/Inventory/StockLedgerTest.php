<?php

namespace Tests\Feature\Inventory;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\SettleOrder;
use App\Inventory\StockLedger;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->venue = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $this->actingAs($this->venue);
    }

    private function gin(): StockItem
    {
        return StockItem::create(['editor_id' => $this->venue->id, 'name' => 'Gin', 'unit' => 'ml', 'reorder_level' => 500]);
    }

    private function openTable(): array
    {
        $table = Table::create(['editor_id' => $this->venue->id, 'table_number' => 1, 'status' => 'open']);
        $session = TableSession::create([
            'table_id' => $table->id, 'session_number' => 1, 'date' => now()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
            'opened_by' => $this->venue->id, 'editor_id' => $this->venue->id,
        ]);

        return [$table, $session];
    }

    public function test_receiving_moves_on_hand_and_weighted_average_cost(): void
    {
        $gin = $this->gin();
        $ledger = app(StockLedger::class);

        $ledger->receive($gin, 750, 0.02, $this->venue, 'Bottle #1');   // 750 ml @ 0.02/ml = 15.00
        $ledger->receive($gin, 750, 0.04, $this->venue, 'Bottle #2');   // 750 ml @ 0.04/ml = 30.00

        $gin->refresh();
        $this->assertEqualsWithDelta(1500, (float) $gin->quantity_on_hand, 0.001);
        $this->assertEqualsWithDelta(0.03, (float) $gin->unit_cost, 0.0001, 'Weighted average of 0.02 and 0.04 at equal volumes.');
        $this->assertFalse($gin->isLow());
    }

    public function test_waste_count_and_adjustment_are_ledgered(): void
    {
        $gin = $this->gin();
        $ledger = app(StockLedger::class);
        $ledger->receive($gin, 1000, 0.03, $this->venue);

        $ledger->waste($gin, 100, $this->venue, 'Spilled');
        $this->assertEqualsWithDelta(900, (float) $gin->fresh()->quantity_on_hand, 0.001);

        $count = $ledger->count($gin, 850, $this->venue);
        $this->assertEqualsWithDelta(-50, (float) $count->quantity, 0.001, 'The count records the variance, not the level.');
        $this->assertEqualsWithDelta(850, (float) $gin->fresh()->quantity_on_hand, 0.001);

        $ledger->adjust($gin, -400, $this->venue, 'Correction');
        $this->assertTrue($gin->fresh()->isLow(), '450 ml is at or below the 500 ml reorder level.');

        $this->assertSame(4, StockMovement::where('stock_item_id', $gin->id)->count());
    }

    public function test_delivery_depletes_the_recipe_once_and_records_cogs(): void
    {
        $gin = $this->gin();
        $tonic = StockItem::create(['editor_id' => $this->venue->id, 'name' => 'Tonic', 'unit' => 'unit']);
        $ledger = app(StockLedger::class);
        $ledger->receive($gin, 1000, 0.03, $this->venue);
        $ledger->receive($tonic, 24, 0.50, $this->venue);

        $gt = Product::create(['name' => 'Gin Tonic', 'price' => 8.00, 'editor_id' => $this->venue->id, 'is_available' => true]);
        ProductComponent::create(['product_id' => $gt->id, 'stock_item_id' => $gin->id, 'quantity_per_unit' => 50]);
        ProductComponent::create(['product_id' => $gt->id, 'stock_item_id' => $tonic->id, 'quantity_per_unit' => 1]);

        [$table, $session] = $this->openTable();
        $order = app(CreateOrder::class)->handle($table, $session, [$gt->id => 2]);

        // Pending: nothing moves.
        $this->assertEqualsWithDelta(1000, (float) $gin->fresh()->quantity_on_hand, 0.001);

        app(ChangeOrderStatus::class)->handle($order, 'delivered');
        $this->assertEqualsWithDelta(900, (float) $gin->fresh()->quantity_on_hand, 0.001, '2 × 50 ml');
        $this->assertEqualsWithDelta(22, (float) $tonic->fresh()->quantity_on_hand, 0.001);
        $this->assertEqualsWithDelta(2.00, (float) $order->items()->first()->cost_amount, 0.0001, '50 ml × 0.03 + 1 × 0.50');

        // Idempotent: settling a delivered order does not deplete again.
        app(SettleOrder::class)->handle($order->fresh(), $this->venue, 'cash');
        app(StockLedger::class)->syncOrder($order->fresh());
        $this->assertEqualsWithDelta(900, (float) $gin->fresh()->quantity_on_hand, 0.001);
    }

    public function test_un_delivering_reverses_and_re_delivering_depletes_again(): void
    {
        $gin = $this->gin();
        app(StockLedger::class)->receive($gin, 1000, 0.03, $this->venue);
        $shot = Product::create(['name' => 'Gin shot', 'price' => 3.00, 'editor_id' => $this->venue->id, 'is_available' => true]);
        ProductComponent::create(['product_id' => $shot->id, 'stock_item_id' => $gin->id, 'quantity_per_unit' => 40]);
        [$table, $session] = $this->openTable();
        $order = app(CreateOrder::class)->handle($table, $session, [$shot->id => 1]);

        app(ChangeOrderStatus::class)->handle($order, 'delivered');
        $this->assertEqualsWithDelta(960, (float) $gin->fresh()->quantity_on_hand, 0.001);

        app(ChangeOrderStatus::class)->handle($order->fresh(), 'pending');
        $this->assertEqualsWithDelta(1000, (float) $gin->fresh()->quantity_on_hand, 0.001, 'Reversed.');
        $this->assertNull($order->items()->first()->cost_amount);

        app(ChangeOrderStatus::class)->handle($order->fresh(), 'delivered');
        $this->assertEqualsWithDelta(960, (float) $gin->fresh()->quantity_on_hand, 0.001, 'Depleted again, exactly once.');
    }

    public function test_cancelled_orders_never_deplete_and_products_without_recipes_are_ignored(): void
    {
        $gin = $this->gin();
        app(StockLedger::class)->receive($gin, 1000, 0.03, $this->venue);
        $shot = Product::create(['name' => 'Gin shot', 'price' => 3.00, 'editor_id' => $this->venue->id, 'is_available' => true]);
        ProductComponent::create(['product_id' => $shot->id, 'stock_item_id' => $gin->id, 'quantity_per_unit' => 40]);
        $water = Product::create(['name' => 'Agua', 'price' => 1.00, 'editor_id' => $this->venue->id, 'is_available' => true]);
        [$table, $session] = $this->openTable();

        $cancelled = app(CreateOrder::class)->handle($table, $session, [$shot->id => 1]);
        app(ChangeOrderStatus::class)->handle($cancelled, 'cancelled');
        $this->assertEqualsWithDelta(1000, (float) $gin->fresh()->quantity_on_hand, 0.001);

        $plain = app(CreateOrder::class)->handle($table, $session, [$water->id => 1]);
        app(ChangeOrderStatus::class)->handle($plain, 'delivered');
        $this->assertNull($plain->items()->first()->cost_amount, 'No recipe: no COGS, no movement.');
        $this->assertSame(1, StockMovement::count(), 'Only the purchase.');
    }
}
