<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiUser;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use ActsAsApiUser, InteractsWithTenants, RefreshDatabase;

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

    public function test_analytics_are_editor_only_and_range_validated(): void
    {
        $editor = $this->makeEditor();
        $staff = $this->makeStaff($editor);

        $this->apiActingAs($staff);
        $this->getJson('/api/v1/analytics/summary')->assertStatus(403);

        $this->apiActingAs($editor);
        $this->getJson('/api/v1/analytics/summary?range=yesterday')->assertStatus(422);
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
