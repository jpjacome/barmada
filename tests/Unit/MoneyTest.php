<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_sums_without_binary_drift(): void
    {
        // 0.1 + 0.2 + ... as floats is 0.30000000000000004-style noise.
        $this->assertSame(0.3, Money::sum([0.1, 0.2]));
        $this->assertSame(3.0, Money::sum(array_fill(0, 30, 0.1)));
    }

    public function test_accepts_the_shapes_the_database_hands_back(): void
    {
        // MySQL decimals arrive as strings, SQLite as floats; nulls happen.
        $this->assertSame(7.5, Money::sum(['2.50', 2.5, '2.50', null]));
    }

    public function test_subtract_and_zero_detection(): void
    {
        $this->assertSame(0.0, Money::subtract('10.00', 10));
        $this->assertTrue(Money::isZero(Money::subtract(0.3, Money::sum([0.1, 0.2]))));
        $this->assertFalse(Money::isZero(0.01));
        $this->assertTrue(Money::equals('4.5', 4.50));
    }

    public function test_rounds_half_up_at_two_decimals(): void
    {
        $this->assertSame(1.01, Money::round(1.005));
        $this->assertSame(2.68, Money::round(2.675));
        $this->assertSame(101, Money::toCents('1.005'));
    }
}
