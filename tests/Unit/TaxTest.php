<?php

namespace Tests\Unit;

use App\Support\Tax;
use Tests\TestCase;

class TaxTest extends TestCase
{
    public function test_inclusive_split_recovers_net_and_tax_to_the_cent(): void
    {
        // A USD 4.50 beer with IVA 15% inside: net 3.91, tax 0.59.
        [$net, $tax] = Tax::splitInclusive(4.50, 1500);
        $this->assertSame(3.91, $net);
        $this->assertSame(0.59, $tax);
        $this->assertSame(4.50, round($net + $tax, 2));
    }

    public function test_zero_rate_has_no_tax(): void
    {
        $this->assertSame([2.50, 0.0], Tax::splitInclusive('2.50', 0));
        $this->assertSame([2.50, 0.0], Tax::addToNet('2.50', 0));
    }

    public function test_exclusive_prices_add_tax_on_top(): void
    {
        [$gross, $tax] = Tax::addToNet(10.00, 1500);
        $this->assertSame(11.50, $gross);
        $this->assertSame(1.50, $tax);
    }

    public function test_unit_snapshot_for_inclusive_and_exclusive_menus(): void
    {
        $inclusive = Tax::unitSnapshot(4.50, '4', true);
        $this->assertSame(4.50, $inclusive['price']);
        $this->assertSame(3.91, $inclusive['net_price']);
        $this->assertSame(0.59, $inclusive['tax_amount']);
        $this->assertSame(1500, $inclusive['tax_rate_bp']);

        $exclusive = Tax::unitSnapshot(4.50, '4', false);
        $this->assertSame(5.18, $exclusive['price'], 'Guest owes the menu price plus IVA.');
        $this->assertSame(4.50, $exclusive['net_price']);
        $this->assertSame(0.68, $exclusive['tax_amount']);
    }

    public function test_service_charge_is_on_the_net_base_and_capped_at_ten_percent(): void
    {
        $this->assertSame(10.00, Tax::serviceCharge(100.00, 1000));
        $this->assertSame(5.00, Tax::serviceCharge(100.00, 500));
        $this->assertSame(10.00, Tax::serviceCharge(100.00, 2500), 'Decreto 1269 caps the propina at 10%.');
        $this->assertSame(0.0, Tax::serviceCharge(100.00, 0));
    }
}
