<?php

namespace App\Actions\Settings;

use App\Models\User;

/**
 * Applies a venue's business settings (currency, guest language,
 * timezone, business-day cutoff, tax defaults, service charge) to the
 * editor account. These columns are not mass-assignable by design;
 * values arrive validated from the calling boundary and are set
 * explicitly.
 */
class UpdateBusinessSettings
{
    /**
     * @param  array{
     *     currency_symbol: string,
     *     locale: string,
     *     business_timezone?: ?string,
     *     day_cutoff_hour?: int|string|null,
     *     default_tax_code?: ?string,
     *     prices_include_tax?: bool|int|string|null,
     *     service_charge_enabled?: bool|int|string|null,
     *     service_charge_rate_bp?: int|string|null,
     * }  $validated
     */
    public function handle(User $editor, array $validated): User
    {
        $editor->forceFill([
            'currency_symbol' => $validated['currency_symbol'],
            'locale' => $validated['locale'],
        ]);

        foreach (['business_timezone', 'default_tax_code'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $editor->forceFill([$key => $validated[$key]]);
            }
        }

        foreach (['day_cutoff_hour', 'service_charge_rate_bp'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $editor->forceFill([$key => (int) $validated[$key]]);
            }
        }

        foreach (['prices_include_tax', 'service_charge_enabled'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $editor->forceFill([$key => filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        $editor->save();

        return $editor->refresh();
    }

    /**
     * Validation rules shared by the web form and the API.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $codes = implode(',', array_keys(config('fiscal.iva_codes', [])));
        $maxBp = (int) config('fiscal.service_charge.max_rate_bp', 1000);

        return [
            'currency_symbol' => ['required', 'string', 'max:5', 'regex:/^[^<>"\']+$/u'],
            'locale' => ['required', 'in:en,es'],
            'business_timezone' => ['nullable', 'timezone:all'],
            'day_cutoff_hour' => ['nullable', 'integer', 'min:0', 'max:12'],
            'default_tax_code' => ['nullable', 'in:'.$codes],
            'prices_include_tax' => ['nullable', 'boolean'],
            'service_charge_enabled' => ['nullable', 'boolean'],
            'service_charge_rate_bp' => ['nullable', 'integer', 'min:0', 'max:'.$maxBp],
        ];
    }
}
