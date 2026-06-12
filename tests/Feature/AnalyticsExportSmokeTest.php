<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Smoke coverage for the analytics exports — these render the full
 * aggregation pipeline end to end, so a broken variable or query in any
 * monthly loop fails here instead of in production.
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

    public function test_analytics_dashboard_renders(): void
    {
        $editor = $this->makeEditor();
        $this->actingAs($editor)->get('/analytics')->assertOk();
    }
}
