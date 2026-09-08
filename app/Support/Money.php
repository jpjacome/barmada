<?php

namespace App\Support;

/**
 * Two-decimal money arithmetic without float drift.
 *
 * Prices live in decimal(8,2)/decimal(10,2) columns and arrive in PHP as
 * numeric strings under MySQL and floats under SQLite. Summing them with
 * a bare `+` accumulates binary error that then gets compared with `===`
 * or printed as -0.00. Everything here works in integer cents and hands
 * back a float rounded to two places, which is what the columns store.
 */
final class Money
{
    /** @param iterable<mixed> $amounts */
    public static function sum(iterable $amounts): float
    {
        $cents = 0;
        foreach ($amounts as $amount) {
            $cents += self::toCents($amount);
        }

        return self::fromCents($cents);
    }

    public static function add(mixed $a, mixed $b): float
    {
        return self::fromCents(self::toCents($a) + self::toCents($b));
    }

    public static function subtract(mixed $a, mixed $b): float
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    public static function round(mixed $amount): float
    {
        return self::fromCents(self::toCents($amount));
    }

    public static function isZero(mixed $amount): bool
    {
        return self::toCents($amount) === 0;
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        return self::toCents($a) === self::toCents($b);
    }

    public static function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        // Round to two decimals FIRST: PHP's round() pre-rounds the decimal
        // representation, so 1.005 -> 1.01 and 2.675 -> 2.68, whereas
        // multiplying the raw float by 100 gives 100.4999... -> 100.
        return (int) round(round((float) $amount, 2, PHP_ROUND_HALF_UP) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }
}
