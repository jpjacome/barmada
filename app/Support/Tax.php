<?php

namespace App\Support;

use App\Models\Product;
use App\Models\TaxRateOverride;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Tax resolution and arithmetic.
 *
 * Rates are basis points. Every split rounds to the cent at the line, and
 * document totals are sums of lines — so what the guest sees, what the
 * bill prints and what a fiscal document later emits all agree.
 */
final class Tax
{
    /**
     * The IVA code that applies to a product for a venue at a moment:
     * product override → venue default → platform default, then any
     * date-bounded override (venue-specific first, then platform-wide).
     */
    public static function codeFor(?Product $product, ?User $venue, ?CarbonInterface $at = null): string
    {
        // '0' (IVA 0%) is a legitimate code and falsy in PHP — never use ?:
        $code = self::present($product?->tax_code)
            ?? self::present($venue?->default_tax_code)
            ?? (string) config('fiscal.default_iva_code');

        $at = $at ?: now();
        $day = $at->toDateString();

        $override = TaxRateOverride::query()
            ->where('from_code', $code)
            ->whereDate('starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->where(fn ($q) => $q->whereNull('editor_id')->when($venue, fn ($qq) => $qq->orWhere('editor_id', $venue->id)))
            ->orderByRaw('editor_id IS NULL') // venue-specific rows first
            ->first();

        return $override ? $override->to_code : $code;
    }

    private static function present(mixed $code): ?string
    {
        return $code === null || $code === '' ? null : (string) $code;
    }

    public static function rateBp(string $code): int
    {
        return (int) (config("fiscal.iva_codes.{$code}.rate_bp") ?? 0);
    }

    public static function label(string $code): string
    {
        return (string) (config("fiscal.iva_codes.{$code}.label") ?? "IVA ({$code})");
    }

    /** @return array<string, array{rate_bp:int,label:string}> */
    public static function catalogue(): array
    {
        return config('fiscal.iva_codes', []);
    }

    /**
     * Split a tax-inclusive gross amount into [net, tax].
     */
    public static function splitInclusive(mixed $gross, int $rateBp): array
    {
        $grossCents = Money::toCents($gross);
        if ($rateBp <= 0) {
            return [Money::fromCents($grossCents), 0.0];
        }
        $netCents = (int) round($grossCents / (1 + $rateBp / 10000));

        return [Money::fromCents($netCents), Money::fromCents($grossCents - $netCents)];
    }

    /**
     * Add tax to a net amount: returns [gross, tax].
     */
    public static function addToNet(mixed $net, int $rateBp): array
    {
        $netCents = Money::toCents($net);
        $taxCents = (int) round($netCents * $rateBp / 10000);

        return [Money::fromCents($netCents + $taxCents), Money::fromCents($taxCents)];
    }

    /**
     * Per-unit snapshot for an order item from a menu price.
     *
     * @return array{price:float,net_price:float,tax_amount:float,tax_code:string,tax_rate_bp:int}
     */
    public static function unitSnapshot(mixed $menuPrice, string $code, bool $pricesIncludeTax): array
    {
        $rateBp = self::rateBp($code);

        if ($pricesIncludeTax) {
            [$net, $tax] = self::splitInclusive($menuPrice, $rateBp);
            $gross = Money::round($menuPrice);
        } else {
            [$gross, $tax] = self::addToNet($menuPrice, $rateBp);
            $net = Money::round($menuPrice);
        }

        return [
            'price' => $gross,          // what the guest owes for this unit
            'net_price' => $net,
            'tax_amount' => $tax,
            'tax_code' => $code,
            'tax_rate_bp' => $rateBp,
        ];
    }

    /**
     * Service charge ("10% de servicio") on a pre-tax subtotal, capped.
     */
    public static function serviceCharge(mixed $netSubtotal, int $rateBp): float
    {
        $rateBp = max(0, min((int) config('fiscal.service_charge.max_rate_bp', 1000), $rateBp));

        return Money::fromCents((int) round(Money::toCents($netSubtotal) * $rateBp / 10000));
    }
}
