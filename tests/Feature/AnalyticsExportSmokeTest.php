<?php

namespace Tests\Feature;

use App\Support\VenueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Smoke coverage for the analytics exports — these render the full
 * aggregation pipeline end to end, so a broken variable or query in any
 * monthly loop fails here instead of in production.
 *
 * The exports read VenueAnalytics and nothing else, so the CSV is also
 * where we check that the numbers leaving the app are the numbers the
 * read models produced.
 */
class AnalyticsExportSmokeTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_pdf_and_csv_exports_render_for_an_editor_with_data(): void
    {
        $editor = $this->makeEditor();
        $product = $this->makeProductFor($editor, ['price' => 5.00]);
        $order = $this->makeOrderFor($editor, ['total_amount' => 10.00, 'created_at' => now()]);
        $this->addItem($order, $product);

        $this->actingAs($editor)
            ->get('/analytics/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $csv = $this->actingAs($editor)->get('/analytics/csv');
        $csv->assertOk();
        $this->assertStringContainsString('Monthly Stats', $csv->streamedContent());
    }

    public function test_csv_carries_the_same_numbers_the_read_models_report(): void
    {
        $editor = $this->makeEditor();
        // Pin the hour: the business day for a default venue (UTC, cutoff
        // 0) starts at midnight, and the range's upper bound is now().
        $this->travelTo(now()->setTime(14, 0));

        $mojito = $this->makeProductFor($editor, ['name' => 'Mojito', 'price' => 6.00]);
        $beer = $this->makeProductFor($editor, ['name' => 'Pilsener', 'price' => 2.50]);

        $order = $this->makeOrderFor($editor, [
            'status' => 'delivered', 'total_amount' => 14.50, 'created_at' => now()->subHour(),
        ]);
        $this->addItem($order, $mojito, 2);
        $this->addItem($order, $beer);

        // Cancelled work must not reach the export either.
        $this->makeOrderFor($editor, [
            'status' => 'cancelled', 'total_amount' => 99.00, 'created_at' => now()->subHour(),
        ]);

        $summary = VenueAnalytics::summary($editor, 'today');
        $this->assertSame(14.5, $summary['total_sales']);
        $this->assertSame('Mojito', $summary['top_product']);

        $csv = $this->actingAs($editor)->get('/analytics/csv');
        $csv->assertOk();
        $body = $csv->streamedContent();

        // Stats by Range reproduces summary() verbatim, cancelled excluded.
        $this->assertStringContainsString('today,14.5,1,Mojito,14.5,', $body);
        // Product Sales Matrix reproduces productMatrix().
        $this->assertStringContainsString('Mojito,2,2,2', $body);
        $this->assertStringContainsString('Pilsener,1,1,1', $body);
        // The cancelled order's 99.00 appears nowhere.
        $this->assertStringNotContainsString('99', $body);
        // The mixed sections still render their nested blocks.
        $this->assertStringContainsString('Service & Operations (Month)', $body);
        $this->assertStringContainsString('Product Category Stats (Month)', $body);
        $this->assertStringContainsString('top_products', $body);
    }

    public function test_analytics_dashboard_renders(): void
    {
        $editor = $this->makeEditor();
        $this->actingAs($editor)->get('/analytics')->assertOk();
    }
}
