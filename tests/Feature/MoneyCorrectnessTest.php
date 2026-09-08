<?php

namespace Tests\Feature;

use App\Livewire\AllOrdersList;
use App\Livewire\TablesList;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Money has to add up.
 *
 * These cover the correctness bugs found in the September 2026 audit:
 * editing an order collapsed multi-unit lines so the bill undercharged,
 * and settling or closing a table reached through every session the
 * table had ever had instead of the one in front of the staff member.
 */
class MoneyCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        return User::factory()->create([
            'is_editor' => true,
            'username' => 'bar'.uniqid(),
        ]);
    }

    private function makeTable(User $editor, int $number, string $status = 'open'): Table
    {
        return Table::create([
            'editor_id' => $editor->id,
            'table_number' => $number,
            'status' => $status,
        ]);
    }

    private function makeSession(Table $table, string $status = 'open'): TableSession
    {
        return TableSession::create([
            'table_id' => $table->id,
            'session_number' => TableSession::where('table_id', $table->id)->count() + 1,
            'date' => now()->toDateString(),
            'unique_token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => $status,
            'opened_at' => now(),
            'opened_by' => $table->editor_id,
            'editor_id' => $table->editor_id,
        ]);
    }

    private function makeProduct(User $editor, string $name, float $price): Product
    {
        return Product::create([
            'name' => $name,
            'price' => $price,
            'editor_id' => $editor->id,
            'is_available' => true,
        ]);
    }

    private function makeOrder(Table $table, ?TableSession $session, Product $product, int $units): Order
    {
        $order = Order::create([
            'table_id' => $table->id,
            'table_session_id' => $session?->id,
            'status' => 'pending',
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
            'editor_id' => $table->editor_id,
        ]);

        $total = 0;
        for ($i = 0; $i < $units; $i++) {
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $product->price,
                'is_paid' => false,
                'item_index' => $i,
            ]);
            $total += $product->price;
        }

        $order->update(['total_amount' => $total, 'amount_left' => $total]);

        return $order->refresh();
    }

    /** The bug: a 3x line was written as one row and billed as 1x. */
    public function test_editing_an_order_writes_one_row_per_unit(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 1);
        $session = $this->makeSession($table);
        $product = $this->makeProduct($editor, 'Pilsener', 2.50);
        $order = $this->makeOrder($table, $session, $product, 1);

        $this->actingAs($editor);

        Livewire::test(AllOrdersList::class)
            ->call('openStatusModal', $order->id)
            ->set('editingOrder.products.'.$product->id, 3)
            ->call('saveChanges');

        $items = OrderItem::where('order_id', $order->id)->get();

        $this->assertCount(3, $items, 'Three units must be three rows.');
        $this->assertTrue($items->every(fn ($item) => (int) $item->quantity === 1));
        $this->assertSame([0, 1, 2], $items->pluck('item_index')->sort()->values()->all());
    }

    /** The consequence: the bill and the stored total must agree. */
    public function test_editing_an_order_recomputes_the_stored_total(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 2);
        $session = $this->makeSession($table);
        $product = $this->makeProduct($editor, 'Gin Tonic', 6.00);
        $order = $this->makeOrder($table, $session, $product, 1);

        $this->actingAs($editor);

        Livewire::test(AllOrdersList::class)
            ->call('openStatusModal', $order->id)
            ->set('editingOrder.products.'.$product->id, 4)
            ->call('saveChanges');

        $order->refresh();
        $itemsTotal = OrderItem::where('order_id', $order->id)->sum('price');

        $this->assertEqualsWithDelta(24.00, (float) $itemsTotal, 0.001);
        $this->assertEqualsWithDelta(24.00, (float) $order->total_amount, 0.001);
        $this->assertEqualsWithDelta(24.00, (float) $order->amount_left, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->amount_paid, 0.001);
    }

    /** Settling tonight's bill must not touch a previous session. */
    public function test_settling_a_table_only_settles_the_current_session(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 3);
        $product = $this->makeProduct($editor, 'Cuba Libre', 5.00);

        $past = $this->makeSession($table, 'closed');
        $pastOrder = $this->makeOrder($table, $past, $product, 2);

        $current = $this->makeSession($table);
        $currentOrder = $this->makeOrder($table, $current, $product, 1);

        (new \App\Actions\Tables\SettleTable())->handle($table);

        $this->assertTrue(
            $currentOrder->refresh()->items->every(fn ($item) => (bool) $item->is_paid),
            'Current session should be settled.'
        );
        $this->assertTrue(
            $pastOrder->refresh()->items->every(fn ($item) => ! $item->is_paid),
            'A previous session must be left alone.'
        );
    }

    /** An old unpaid item must not make a table permanently uncloseable. */
    public function test_closing_a_table_ignores_unpaid_items_from_past_sessions(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 4);
        $product = $this->makeProduct($editor, 'Agua', 1.00);

        $past = $this->makeSession($table, 'closed');
        $this->makeOrder($table, $past, $product, 1); // never paid

        $current = $this->makeSession($table);
        $currentOrder = $this->makeOrder($table, $current, $product, 1);
        $currentOrder->items()->update(['is_paid' => true]);

        $this->assertFalse(
            (new \App\Actions\Tables\CloseTable())->hasUnpaidBalance($table),
            'Only the current session should block closing.'
        );
    }

    public function test_fully_paid_check_is_scoped_to_the_current_session(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 5);
        $product = $this->makeProduct($editor, 'Cerveza', 3.00);

        $past = $this->makeSession($table, 'closed');
        $this->makeOrder($table, $past, $product, 1); // unpaid, historical

        $current = $this->makeSession($table);
        $currentOrder = $this->makeOrder($table, $current, $product, 2);
        $currentOrder->items()->update(['is_paid' => true]);

        $this->actingAs($editor);

        $this->assertTrue(
            Livewire::test(TablesList::class)->instance()->isTableFullyPaid($table->id)
        );
    }

    /** A zero-priced item used to break the strict === 0 comparison. */
    public function test_fully_paid_check_survives_zero_priced_items(): void
    {
        $editor = $this->makeEditor();
        $table = $this->makeTable($editor, 6);
        $free = $this->makeProduct($editor, 'Cortesia', 0.00);

        $session = $this->makeSession($table);
        $order = $this->makeOrder($table, $session, $free, 1);
        $order->items()->update(['is_paid' => true]);

        $this->actingAs($editor);

        $this->assertTrue(
            Livewire::test(TablesList::class)->instance()->isTableFullyPaid($table->id)
        );
    }
}
