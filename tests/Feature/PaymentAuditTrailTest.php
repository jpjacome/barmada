<?php

namespace Tests\Feature;

use App\Actions\Orders\RecalculateOrderTotals;
use App\Actions\Orders\SettleOrder;
use App\Actions\Orders\ToggleItemPaid;
use App\Actions\Tables\SettleTable;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every payment records who took the money, when, and how — and the
 * order's money columns always equal what its items say.
 */
class PaymentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;
    private User $staff;
    private Table $table;
    private TableSession $session;
    private Product $beer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $this->staff = User::factory()->create(['username' => 'mesero'.uniqid()]);
        $this->staff->forceFill(['is_staff' => true, 'editor_id' => $this->editor->id])->save();

        $this->table = Table::create(['editor_id' => $this->editor->id, 'table_number' => 7, 'status' => 'open']);
        $this->session = TableSession::create([
            'table_id' => $this->table->id, 'session_number' => 1, 'date' => now()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
            'opened_by' => $this->editor->id, 'editor_id' => $this->editor->id,
        ]);
        $this->beer = Product::create(['name' => 'Pilsener', 'price' => 2.50, 'editor_id' => $this->editor->id, 'is_available' => true]);
    }

    private function order(int $units, ?TableSession $session = null): Order
    {
        $order = Order::create([
            'table_id' => $this->table->id, 'table_session_id' => ($session ?? $this->session)->id,
            'status' => 'pending', 'total_amount' => 0, 'amount_paid' => 0, 'amount_left' => 0,
            'editor_id' => $this->editor->id,
        ]);
        for ($i = 0; $i < $units; $i++) {
            $order->items()->create(['product_id' => $this->beer->id, 'quantity' => 1, 'price' => 2.50, 'is_paid' => false, 'item_index' => $i]);
        }

        return app(RecalculateOrderTotals::class)->handle($order->load('items'));
    }

    public function test_ticking_an_item_records_actor_time_and_method(): void
    {
        $order = $this->order(2);

        $item = app(ToggleItemPaid::class)->handle($order, $this->beer->id, 0, $this->staff, 'card');

        $this->assertTrue($item->is_paid);
        $this->assertNotNull($item->paid_at);
        $this->assertSame($this->staff->id, $item->paid_by);
        $this->assertSame('card', $item->payment_method);

        $log = ActivityLog::withoutGlobalScopes()->where('order_id', $order->id)->latest('id')->first();
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame('card', $log->metadata['payment_method']);

        $order->refresh();
        $this->assertEqualsWithDelta(5.00, (float) $order->total_amount, 0.001);
        $this->assertEqualsWithDelta(2.50, (float) $order->amount_paid, 0.001);
        $this->assertEqualsWithDelta(2.50, (float) $order->amount_left, 0.001);
    }

    public function test_unticking_clears_the_payment_record(): void
    {
        $order = $this->order(1);
        app(ToggleItemPaid::class)->handle($order, $this->beer->id, 0, $this->staff, 'cash');
        $item = app(ToggleItemPaid::class)->handle($order, $this->beer->id, 0, $this->staff);

        $this->assertFalse($item->is_paid);
        $this->assertNull($item->paid_at);
        $this->assertNull($item->paid_by);
        $this->assertNull($item->payment_method);
        $this->assertEqualsWithDelta(2.50, (float) $order->fresh()->amount_left, 0.001);
    }

    public function test_settling_an_order_logs_only_the_money_that_changed_hands(): void
    {
        $order = $this->order(3);
        app(ToggleItemPaid::class)->handle($order, $this->beer->id, 0, $this->staff, 'cash');

        app(SettleOrder::class)->handle($order->fresh(), $this->editor, 'card');

        $log = ActivityLog::withoutGlobalScopes()->where('order_id', $order->id)->latest('id')->first();
        $this->assertEqualsWithDelta(5.00, (float) $log->amount, 0.001, 'Two of three items were newly paid.');
        $this->assertSame($this->editor->id, $log->user_id);
        $this->assertSame(2, $log->metadata['items_newly_paid']);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertEqualsWithDelta(0.0, (float) $order->amount_left, 0.001);
        $this->assertTrue($order->items->every(fn ($i) => $i->is_paid && $i->paid_at !== null));
    }

    public function test_settling_a_table_records_the_actor_and_leaves_past_sessions_alone(): void
    {
        $past = TableSession::create([
            'table_id' => $this->table->id, 'session_number' => 0, 'date' => now()->subDay()->toDateString(),
            'unique_token' => (string) Str::uuid(), 'status' => 'closed', 'opened_at' => now()->subDay(),
            'opened_by' => $this->editor->id, 'editor_id' => $this->editor->id,
        ]);
        $old = $this->order(1, $past);
        $current = $this->order(2);

        app(SettleTable::class)->handle($this->table, $this->staff, 'cash');

        $this->assertFalse($old->fresh()->items->first()->is_paid);
        $this->assertTrue($current->fresh()->items->every(fn ($i) => $i->is_paid && $i->paid_by === $this->staff->id));

        $log = ActivityLog::withoutGlobalScopes()->where('table_id', $this->table->id)->whereNull('order_id')->latest('id')->first();
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertEqualsWithDelta(5.00, (float) $log->amount, 0.001);
    }

    public function test_totals_are_derived_from_items_everywhere(): void
    {
        $order = $this->order(4);
        $order->items()->where('item_index', '<', 2)->update(['is_paid' => true]);

        app(RecalculateOrderTotals::class)->handle($order->fresh());

        $order->refresh();
        $this->assertEqualsWithDelta(10.00, (float) $order->total_amount, 0.001);
        $this->assertEqualsWithDelta(5.00, (float) $order->amount_paid, 0.001);
        $this->assertEqualsWithDelta(5.00, (float) $order->amount_left, 0.001);
    }
}
