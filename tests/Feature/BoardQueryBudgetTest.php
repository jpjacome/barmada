<?php

namespace Tests\Feature;

use App\Livewire\AllOrdersList;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\TableSessionRequest;
use App\Models\User;
use App\Support\PendingApprovals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The orders board and the approval queue are polled every five seconds
 * by every open staff browser and phone. Their query count must not
 * grow with the number of tables or pending orders.
 */
class BoardQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function venueWith(int $tables, int $pendingOrders): User
    {
        $editor = User::factory()->create(['is_editor' => true, 'username' => 'bar'.uniqid()]);
        $beer = Product::create(['name' => 'Pilsener', 'price' => 2.5, 'editor_id' => $editor->id, 'is_available' => true]);

        for ($n = 1; $n <= $tables; $n++) {
            $table = Table::create(['editor_id' => $editor->id, 'table_number' => $n, 'status' => 'open']);
            $session = TableSession::create([
                'table_id' => $table->id, 'session_number' => 1, 'date' => now()->toDateString(),
                'unique_token' => (string) Str::uuid(), 'status' => 'open', 'opened_at' => now(),
                'opened_by' => $editor->id, 'editor_id' => $editor->id,
            ]);
            TableSessionRequest::create([
                'table_session_id' => $session->id, 'table_id' => $table->id, 'ip_address' => "10.0.0.$n",
                'device_token' => str_repeat('a', 30).str_pad((string) $n, 10, '0', STR_PAD_LEFT), 'status' => $n % 2 ? 'approved' : 'pending', 'requested_at' => now(),
            ]);
            if ($n <= $pendingOrders) {
                $order = Order::create([
                    'table_id' => $table->id, 'table_session_id' => $session->id, 'status' => 'pending',
                    'total_amount' => 5, 'amount_paid' => 0, 'amount_left' => 5, 'editor_id' => $editor->id,
                ]);
                $order->items()->create(['product_id' => $beer->id, 'quantity' => 1, 'price' => 2.5, 'is_paid' => false, 'item_index' => 0]);
                $order->items()->create(['product_id' => $beer->id, 'quantity' => 1, 'price' => 2.5, 'is_paid' => false, 'item_index' => 1]);
            }
        }

        return $editor;
    }

    private function countQueries(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_board_poll_query_count_does_not_grow_with_tables(): void
    {
        $small = $this->venueWith(3, 2);
        $this->actingAs($small);
        $component = Livewire::test(AllOrdersList::class);
        $atThree = $this->countQueries(fn () => $component->call('refreshBoard'));

        $large = $this->venueWith(40, 25);
        $this->actingAs($large);
        $component = Livewire::test(AllOrdersList::class);
        $atForty = $this->countQueries(fn () => $component->call('refreshBoard'));

        $this->assertLessThanOrEqual($atThree + 2, $atForty, "Board poll ran {$atForty} queries at 40 tables vs {$atThree} at 3 — an N+1 crept back in.");
    }

    public function test_pending_approvals_run_a_fixed_number_of_queries(): void
    {
        $editor = $this->venueWith(30, 0);
        $this->actingAs($editor);

        $count = $this->countQueries(fn () => PendingApprovals::list());

        $this->assertLessThanOrEqual(5, $count);
        $this->assertCount(15, PendingApprovals::list(), 'Every even-numbered table has one pending device.');
    }
}
