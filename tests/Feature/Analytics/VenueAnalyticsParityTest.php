<?php

namespace Tests\Feature\Analytics;

use App\Support\VenueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * The golden file for the analytics read models.
 *
 * Every number below was produced by the pre-SQL implementation of
 * VenueAnalytics against the deterministic venue in SeedsAnalyticsVenue,
 * with the clock pinned to 2026-09-08 20:00 America/Guayaquil. It pins
 * today's *semantics*, not just today's code: business-day bucketing
 * (cutoff 06:00, so a 01:00 order counts toward the previous day),
 * exclusion of cancelled orders, the descending hour_distribution, the
 * 'Unknown' fallbacks, and the tie-breaking order of every top/least
 * list. A rewrite that changes any of these fails here.
 */
class VenueAnalyticsParityTest extends TestCase
{
    use RefreshDatabase, SeedsAnalyticsVenue;

    private const SUMMARY_KEYS = [
        'total_sales', 'order_count', 'top_product', 'average_order_value',
        'peak_hour', 'hour_distribution',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->analyticsNow());
    }

    /**
     * Compare against a golden value: exact for ints, strings, null and
     * array keys/ordering; within a cent for money, which crosses a
     * float boundary on the way out of SQLite.
     */
    private function assertGolden($expected, $actual, string $path = ''): void
    {
        if (is_array($expected)) {
            $this->assertIsArray($actual, "Expected an array at {$path}");
            $this->assertSame(
                array_keys($expected),
                array_keys($actual),
                "Keys (and their order) differ at {$path}"
            );
            foreach ($expected as $key => $value) {
                $this->assertGolden($value, $actual[$key], $path.'['.$key.']');
            }

            return;
        }

        if (is_float($expected) || is_float($actual)) {
            $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.005, "Value differs at {$path}");

            return;
        }

        $this->assertSame($expected, $actual, "Value differs at {$path}");
    }

    public function test_summary_matches_the_golden_values_for_every_range(): void
    {
        $venue = $this->seedAnalyticsVenue();

        foreach ($this->goldenSummaries() as $range => $expected) {
            $this->assertGolden(
                $expected,
                Arr::only(VenueAnalytics::summary($venue, $range), self::SUMMARY_KEYS),
                "summary({$range})"
            );
        }
    }

    public function test_product_and_category_stats_match_the_golden_values(): void
    {
        $venue = $this->seedAnalyticsVenue();

        foreach ($this->goldenProductStats() as $range => $expected) {
            $this->assertGolden(
                $expected,
                VenueAnalytics::productAndCategoryStats($venue, $range),
                "productAndCategoryStats({$range})"
            );
        }
    }

    public function test_service_ops_match_the_golden_values(): void
    {
        $venue = $this->seedAnalyticsVenue();

        foreach ($this->goldenServiceOps() as $range => $expected) {
            $this->assertGolden(
                $expected,
                VenueAnalytics::serviceOps($venue, $range),
                "serviceOps({$range})"
            );
        }
    }

    public function test_monthly_matches_the_golden_values(): void
    {
        $venue = $this->seedAnalyticsVenue();

        $this->assertGolden($this->goldenMonthly(), VenueAnalytics::monthly($venue), 'monthly()');
    }

    public function test_product_matrix_matches_the_golden_values(): void
    {
        $venue = $this->seedAnalyticsVenue();

        $this->assertGolden($this->goldenProductMatrix(), VenueAnalytics::productMatrix($venue), 'productMatrix()');
    }

    // --- Golden values -------------------------------------------------

    private function goldenSummaries(): array
    {
        return require __DIR__.'/golden/summaries.php';
    }

    private function goldenProductStats(): array
    {
        return require __DIR__.'/golden/product_stats.php';
    }

    private function goldenServiceOps(): array
    {
        return require __DIR__.'/golden/service_ops.php';
    }

    private function goldenMonthly(): array
    {
        return require __DIR__.'/golden/monthly.php';
    }

    private function goldenProductMatrix(): array
    {
        return require __DIR__.'/golden/product_matrix.php';
    }
}
