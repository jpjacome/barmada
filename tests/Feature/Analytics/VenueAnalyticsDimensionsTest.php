<?php

namespace Tests\Feature\Analytics;

use App\Support\VenueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dimensions the read models grew once every metric was computed in
 * SQL: margin, how guests actually paid, who took the money, and the
 * per-month figures an accountant needs for the IVA return.
 *
 * Same deterministic venue and pinned clock as the parity test.
 */
class VenueAnalyticsDimensionsTest extends TestCase
{
    use RefreshDatabase, SeedsAnalyticsVenue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->analyticsNow());
    }

    public function test_summary_decomposes_the_money_and_prices_it_against_cost(): void
    {
        $venue = $this->seedAnalyticsVenue();
        $summary = VenueAnalytics::summary($venue, 'today');

        $this->assertSame(62.5, $summary['total_sales']);
        $this->assertSame(14.53, $summary['cogs']);
        $this->assertSame(47.97, $summary['gross_margin']);
        $this->assertSame(76.75, $summary['margin_pct']);
        $this->assertSame(55.41, $summary['subtotal']);
        $this->assertSame(7.09, $summary['tax_total']);
        $this->assertSame(0.74, $summary['service_charge_total']);

        // The decomposition has to add back up to what the guests owed.
        $this->assertEqualsWithDelta(
            $summary['total_sales'],
            $summary['subtotal'] + $summary['tax_total'],
            0.01
        );
        $this->assertEqualsWithDelta(
            $summary['gross_margin'],
            $summary['total_sales'] - $summary['cogs'],
            0.01
        );
    }

    public function test_margin_pct_is_null_rather_than_a_division_by_zero(): void
    {
        $venue = $this->seedAnalyticsVenue(1, 0);
        $summary = VenueAnalytics::summary($venue, 'today');

        $this->assertSame(0, $summary['order_count']);
        $this->assertSame(0.0, $summary['total_sales']);
        $this->assertNull($summary['margin_pct']);
    }

    public function test_payment_mix_splits_paid_items_by_method_and_reports_what_is_owed(): void
    {
        $venue = $this->seedAnalyticsVenue();
        $mix = VenueAnalytics::paymentMix($venue, 'today');

        $this->assertSame(
            [
                ['method' => 'unspecified', 'total' => 22.0, 'units' => 4, 'lines' => 2],
                ['method' => 'card', 'total' => 20.0, 'units' => 4, 'lines' => 2],
                ['method' => 'cash', 'total' => 11.5, 'units' => 2, 'lines' => 2],
            ],
            $mix['methods']
        );
        $this->assertSame(53.5, $mix['paid_total']);
        $this->assertSame(9.0, $mix['unpaid_total']);

        // Paid plus owed is everything sold in the range.
        $this->assertEqualsWithDelta(62.5, $mix['paid_total'] + $mix['unpaid_total'], 0.01);
    }

    public function test_payment_mix_over_a_longer_range_covers_every_method(): void
    {
        $venue = $this->seedAnalyticsVenue();
        $mix = VenueAnalytics::paymentMix($venue, 'month');

        $this->assertSame(
            ['unspecified', 'card', 'cash', 'transfer'],
            array_column($mix['methods'], 'method')
        );
        $this->assertSame(429.0, $mix['paid_total']);
        $this->assertSame(157.0, $mix['unpaid_total']);
    }

    public function test_staff_accountability_names_the_actor_behind_every_payment(): void
    {
        $venue = $this->seedAnalyticsVenue();

        $this->assertSame(
            [
                ['user_id' => $this->seedStaff[1]->id, 'name' => 'Beto Ruiz', 'payments' => 6, 'amount' => 87.0],
                ['user_id' => $this->seedStaff[0]->id, 'name' => 'Ana Torres', 'payments' => 5, 'amount' => 69.0],
                ['user_id' => null, 'name' => 'unknown', 'payments' => 5, 'amount' => 68.0],
            ],
            VenueAnalytics::staffAccountability($venue, 'month')
        );
    }

    public function test_tax_periods_report_the_iva_return_per_business_month(): void
    {
        $venue = $this->seedAnalyticsVenue();
        $periods = VenueAnalytics::taxPeriods($venue, 3);

        $this->assertSame(['2026-9', '2026-8', '2026-7'], array_keys($periods));

        $september = $periods['2026-9'];
        $this->assertSame('September 2026', $september['label']);
        $this->assertSame(34, $september['order_count']);
        $this->assertSame(516.98, $september['subtotal']);
        $this->assertSame(69.02, $september['tax_total']);
        $this->assertSame(14.42, $september['service_charge_total']);
        $this->assertSame(600.42, $september['grand_total']);
        // Built and authorized both spend a sequence number, so both count.
        $this->assertSame(1, $september['fiscal_documents_count']);

        $this->assertSame(
            [
                ['tax_code' => '0', 'net' => 56.0, 'tax' => 0.0],
                ['tax_code' => '4', 'net' => 460.98, 'tax' => 69.02],
            ],
            $september['tax_by_code']
        );

        // The per-code split must reconstruct the month's own totals.
        $this->assertEqualsWithDelta(
            $september['subtotal'],
            array_sum(array_column($september['tax_by_code'], 'net')),
            0.01
        );
        $this->assertEqualsWithDelta(
            $september['tax_total'],
            array_sum(array_column($september['tax_by_code'], 'tax')),
            0.01
        );
        $this->assertEqualsWithDelta(
            $september['grand_total'],
            $september['subtotal'] + $september['tax_total'] + $september['service_charge_total'],
            0.01
        );

        $this->assertSame(2002.6, $periods['2026-8']['subtotal']);
        $this->assertSame(138, $periods['2026-8']['order_count']);
        $this->assertSame(6, $periods['2026-7']['order_count']);
    }

    public function test_tax_periods_report_empty_months_rather_than_omitting_them(): void
    {
        $venue = $this->seedAnalyticsVenue(1, 1);
        $periods = VenueAnalytics::taxPeriods($venue, 4);

        $this->assertCount(4, $periods);
        $this->assertSame(0, $periods['2026-6']['order_count']);
        $this->assertSame(0.0, $periods['2026-6']['subtotal']);
        $this->assertSame([], $periods['2026-6']['tax_by_code']);
        $this->assertSame(0, $periods['2026-6']['fiscal_documents_count']);
    }
}
