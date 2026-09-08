<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Analytics buckets on the venue's business day, which for a default
        // venue (UTC, cutoff 0) starts at UTC midnight. The fixtures below
        // backdate by up to 15 minutes, so a suite run in the first quarter
        // hour of a UTC day pushed them into *yesterday* and three of these
        // tests failed for no reason other than the wall clock. Pin the hour.
        $this->travelTo(now()->setTime(12, 0));
    }

    private function seedSales($editor): void
    {
        [$table, $session] = $this->openTableWithSession($editor);
        // Backdate within "today": the range's upper bound is now() at
        // second precision, so same-second rows would fall outside it.
        $session->update(['opened_at' => now()->subMinutes(15)]);
        $beer = $this->makeProductFor($editor, ['name' => 'Pilsener', 'price' => 2.50]);
        $mojito = $this->makeProductFor($editor, ['name' => 'Mojito', 'price' => 5.50]);

        $orderA = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id,
            'status' => 'delivered', 'total_amount' => 10.50,
            'created_at' => now()->subMinutes(10),
        ]);
        $this->addItem($orderA, $beer, 2);
        $this->addItem($orderA, $mojito);

        $orderB = $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id,
            'status' => 'pending', 'total_amount' => 2.50,
            'created_at' => now()->subMinutes(8),
        ]);
        $this->addItem($orderB, $beer);

        // Cancelled order: present in history, excluded from every figure.
        $this->makeOrderFor($editor, [
            'table_id' => $table->id, 'table_session_id' => $session->id,
            'status' => 'cancelled', 'total_amount' => 99,
            'created_at' => now()->subMinutes(6),
        ]);
    }

    /**
     * The fixtures above are built straight through the factories, which
     * skip the tax snapshot the ordering flow writes. Apply it here so
     * the money endpoints see a realistic decomposition.
     */
    private function snapshotTax($editor): void
    {
        foreach (\App\Models\Order::where('editor_id', $editor->id)->with('items')->get() as $order) {
            $subtotal = 0.0;
            $tax = 0.0;
            foreach ($order->items as $item) {
                $snapshot = \App\Support\Tax::unitSnapshot($item->price, '4', true);
                $item->update([
                    'net_price' => $snapshot['net_price'],
                    'tax_amount' => $snapshot['tax_amount'],
                    'tax_code' => '4',
                    'tax_rate_bp' => $snapshot['tax_rate_bp'],
                ]);
                $subtotal += $snapshot['net_price'] * $item->quantity;
                $tax += $snapshot['tax_amount'] * $item->quantity;
            }
            $order->update([
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($tax, 2),
                'grand_total' => $order->total_amount,
            ]);
        }
    }

    public function test_summary_counts_sales_and_excludes_cancelled(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);

        $response = $this->getJson('/api/v1/analytics/summary?range=today');

        $response->assertOk()
            ->assertJsonPath('range', 'today')
            ->assertJsonPath('summary.order_count', 2)
            ->assertJsonPath('summary.total_sales', 13)
            ->assertJsonPath('summary.average_order_value', 6.5)
            ->assertJsonPath('summary.top_product', 'Pilsener');

        $this->assertNotEmpty($response->json('summary.hour_distribution'));
        $this->assertNotNull($response->json('summary.peak_hour'));
    }

    public function test_product_stats_rank_sellers(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);

        $response = $this->getJson('/api/v1/analytics/products?range=today');

        $response->assertOk();
        $top = $response->json('products.top_products');
        $this->assertSame('Pilsener', $top[0]['name']);
        $this->assertSame(3, $top[0]['quantity']);
    }

    public function test_monthly_returns_twelve_business_months(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());

        $months = $this->getJson('/api/v1/analytics/monthly')->assertOk()->json('months');

        $this->assertCount(12, $months);
    }

    public function test_product_matrix_and_service_ops_smoke(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);

        $matrix = $this->getJson('/api/v1/analytics/product-matrix')->assertOk()->json('matrix');
        $this->assertSame(3, $matrix['Pilsener']['today']);

        $ops = $this->getJson('/api/v1/analytics/service-ops?range=today')->assertOk()->json('service_ops');
        $this->assertSame(1, $ops['sessions_today']);
    }

    public function test_summary_exposes_the_money_decomposition(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);
        $this->snapshotTax($editor);

        $summary = $this->getJson('/api/v1/analytics/summary?range=today')->assertOk()->json('summary');

        // The fixture's prices are IVA-inclusive, so subtotal + tax is
        // what the guests owed, and nothing has been depleted yet.
        $this->assertEqualsWithDelta(13, $summary['subtotal'] + $summary['tax_total'], 0.01);
        $this->assertEquals(0, $summary['cogs']);
        $this->assertEquals(13, $summary['gross_margin']);
        $this->assertEquals(100, $summary['margin_pct']);
        $this->assertEquals(0, $summary['service_charge_total']);
    }

    public function test_payment_mix_reports_methods_and_what_is_still_owed(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);

        // Settle one 2.50 beer in cash; everything else stays unpaid.
        $item = \App\Models\OrderItem::query()->where('price', 2.50)->orderBy('id')->first();
        $item->update(['is_paid' => true, 'payment_method' => 'cash', 'paid_at' => now(), 'paid_by' => $editor->id]);

        $mix = $this->getJson('/api/v1/analytics/payment-mix?range=today')
            ->assertOk()
            ->assertJsonPath('range', 'today')
            ->json('payment_mix');

        $this->assertEquals([['method' => 'cash', 'total' => 5, 'units' => 2, 'lines' => 1]], $mix['methods']);
        $this->assertEquals(5, $mix['paid_total']);
        $this->assertEquals(8, $mix['unpaid_total']);
    }

    public function test_staff_endpoint_attributes_payments_to_actors(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);

        // Backdated for the same reason the sales fixtures are: the
        // range's upper bound is now(), exclusive.
        $log = function (array $attributes) use ($editor) {
            $row = \App\Models\ActivityLog::create($attributes + ['editor_id' => $editor->id, 'description' => 'Logged']);
            $row->created_at = now()->subMinutes(5);
            $row->saveQuietly();
        };
        $log(['type' => 'payment', 'user_id' => $editor->id, 'amount' => 7.25]);
        $log(['type' => 'payment', 'user_id' => null, 'amount' => 2.75]);
        // A non-payment log must not show up here.
        $log(['type' => 'qr_scan', 'user_id' => $editor->id]);

        $staff = $this->getJson('/api/v1/analytics/staff?range=today')->assertOk()->json('staff');

        $this->assertCount(2, $staff);
        $this->assertSame($editor->name, $staff[0]['name']);
        $this->assertEquals(7.25, $staff[0]['amount']);
        $this->assertSame(1, $staff[0]['payments']);
        $this->assertNull($staff[1]['user_id']);
        $this->assertSame('unknown', $staff[1]['name']);
        $this->assertEquals(2.75, $staff[1]['amount']);
    }

    public function test_tax_periods_return_the_requested_number_of_business_months(): void
    {
        $editor = $this->apiActingAs($this->makeEditor());
        $this->seedSales($editor);
        $this->snapshotTax($editor);

        $periods = $this->getJson('/api/v1/analytics/tax-periods')->assertOk()->json('periods');
        $this->assertCount(12, $periods);

        $current = reset($periods);
        $this->assertSame(2, $current['order_count']);
        $this->assertEqualsWithDelta(13, $current['subtotal'] + $current['tax_total'], 0.01);
        $this->assertSame(0, $current['fiscal_documents_count']);
        $this->assertNotEmpty($current['tax_by_code']);

        $this->assertCount(3, $this->getJson('/api/v1/analytics/tax-periods?months=3')->assertOk()->json('periods'));
        $this->getJson('/api/v1/analytics/tax-periods?months=0')->assertStatus(422);
    }

    public function test_analytics_are_editor_only_and_range_validated(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->apiActingAs($staff);
        foreach (['summary', 'payment-mix', 'staff', 'tax-periods'] as $endpoint) {
            $this->getJson('/api/v1/analytics/'.$endpoint)->assertStatus(403);
        }

        $this->apiActingAs($editor);
        $this->getJson('/api/v1/analytics/summary?range=yesterday')->assertStatus(422);
        $this->getJson('/api/v1/analytics/payment-mix?range=yesterday')->assertStatus(422);
    }

    public function test_analytics_are_tenant_bounded(): void
    {
        $editorA = $this->makeEditor();
        $editorB = $this->makeEditor();
        $this->apiActingAs($editorA);
        $this->seedSales($editorA);

        $this->apiActingAs($editorB);
        $this->getJson('/api/v1/analytics/summary?range=today')
            ->assertOk()
            ->assertJsonPath('summary.order_count', 0);
    }
}
