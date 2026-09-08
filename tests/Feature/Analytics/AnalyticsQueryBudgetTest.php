<?php

namespace Tests\Feature\Analytics;

use App\Livewire\AnalyticsDashboard;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Support\VenueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Analytics is the screen a manager leaves open. Its cost must be a
 * function of the venue's catalogue, never of how much the venue has
 * sold: ten times the orders must cost the same number of queries, or
 * the aggregation has leaked back into PHP.
 *
 * The old implementation could pass a naive count-the-queries check
 * while still hydrating every order and item in the range, so these
 * budgets are deliberately tight — a per-row query or a re-added
 * ::find() in a loop breaks them immediately.
 */
class AnalyticsQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const BUDGET = 8;

    private function tally(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** A venue with a fixed catalogue and $orderCount orders spread over 30 days. */
    private function venueWith(int $orderCount): User
    {
        $venue = User::factory()->create(['username' => 'budget'.uniqid()]);
        $venue->forceFill([
            'is_editor' => true, 'editor_id' => $venue->id,
            'business_timezone' => 'America/Guayaquil', 'day_cutoff_hour' => 6,
        ])->save();
        $venue->refresh();

        $category = Category::create(['name' => 'Barra', 'editor_id' => $venue->id]);
        $products = [];
        foreach (['Pilsener', 'Mojito', 'Ceviche'] as $i => $name) {
            $products[] = Product::create([
                'name' => $name, 'price' => 2.5 + $i, 'category_id' => $category->id,
                'editor_id' => $venue->id, 'is_available' => true,
            ]);
        }
        $tables = [];
        for ($n = 1; $n <= 4; $n++) {
            $tables[] = Table::create([
                'editor_id' => $venue->id, 'table_number' => $n, 'status' => 'open',
                'unique_token' => uniqid('t'),
            ]);
        }

        for ($i = 0; $i < $orderCount; $i++) {
            $order = Order::create([
                'editor_id' => $venue->id,
                'table_id' => $tables[$i % 4]->id,
                'status' => $i % 10 === 0 ? 'cancelled' : 'delivered',
                'total_amount' => 10,
                'amount_paid' => 0,
                'amount_left' => 10,
            ]);
            $order->created_at = now()->subDays($i % 25)->subHours($i % 9);
            $order->saveQuietly();

            foreach ([0, 1] as $k) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $products[($i + $k) % 3]->id,
                    'quantity' => 1, 'price' => 5, 'is_paid' => $k === 0,
                    'payment_method' => $k === 0 ? 'cash' : null,
                    'item_index' => $k,
                ]);
            }
        }

        return $venue;
    }

    public function test_every_read_model_stays_within_budget_and_flat_in_order_count(): void
    {
        $small = $this->venueWith(30);
        $large = $this->venueWith(300);

        $calls = [
            'summary' => fn (User $v) => VenueAnalytics::summary($v, '30days'),
            'productAndCategoryStats' => fn (User $v) => VenueAnalytics::productAndCategoryStats($v, '30days'),
            'serviceOps' => fn (User $v) => VenueAnalytics::serviceOps($v, '30days'),
            'monthly' => fn (User $v) => VenueAnalytics::monthly($v),
            'productMatrix' => fn (User $v) => VenueAnalytics::productMatrix($v),
        ];

        foreach ($calls as $name => $call) {
            $atThirty = $this->tally(fn () => $call($small));
            $atThreeHundred = $this->tally(fn () => $call($large));

            $this->assertSame(
                $atThirty,
                $atThreeHundred,
                "{$name} ran {$atThreeHundred} queries at 300 orders vs {$atThirty} at 30 — its cost grows with sales volume."
            );
            $this->assertLessThanOrEqual(
                self::BUDGET,
                $atThreeHundred,
                "{$name} ran {$atThreeHundred} queries, over the budget of ".self::BUDGET.'.'
            );
        }
    }

    public function test_the_new_dimensions_are_within_budget_too(): void
    {
        $small = $this->venueWith(30);
        $large = $this->venueWith(300);

        $calls = [
            'paymentMix' => fn (User $v) => VenueAnalytics::paymentMix($v, '30days'),
            'staffAccountability' => fn (User $v) => VenueAnalytics::staffAccountability($v, '30days'),
            'taxPeriods' => fn (User $v) => VenueAnalytics::taxPeriods($v),
        ];

        foreach ($calls as $name => $call) {
            $atThirty = $this->tally(fn () => $call($small));
            $atThreeHundred = $this->tally(fn () => $call($large));

            $this->assertSame($atThirty, $atThreeHundred, "{$name} scales with order count.");
            $this->assertLessThanOrEqual(self::BUDGET, $atThreeHundred, "{$name} ran {$atThreeHundred} queries.");
        }
    }

    public function test_dashboard_mount_stays_under_sixty_queries(): void
    {
        // mount() runs four aggregators across four ranges, plus the
        // monthly table and the product matrix: 18 read-model calls.
        $venue = $this->venueWith(300);
        $this->actingAs($venue);

        $component = new AnalyticsDashboard;
        $count = $this->tally(fn () => $component->mount());

        $this->assertLessThanOrEqual(
            60,
            $count,
            "AnalyticsDashboard::mount ran {$count} queries at 300 orders."
        );
    }
}
