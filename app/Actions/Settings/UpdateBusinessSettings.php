<?php

namespace App\Actions\Settings;

use App\Models\User;

/**
 * Applies a venue's business settings (currency, guest language,
 * timezone, business-day cutoff) to the editor account. These columns
 * are not mass-assignable by design; values arrive validated from the
 * calling boundary and are set explicitly.
 */
class UpdateBusinessSettings
{
    /**
     * @param  array{currency_symbol: string, locale: string, business_timezone?: ?string, day_cutoff_hour?: int|string|null}  $validated
     */
    public function handle(User $editor, array $validated): User
    {
        $editor->forceFill([
            'currency_symbol' => $validated['currency_symbol'],
            'locale' => $validated['locale'],
        ]);

        if (array_key_exists('business_timezone', $validated) && $validated['business_timezone'] !== null) {
            $editor->forceFill(['business_timezone' => $validated['business_timezone']]);
        }

        if (array_key_exists('day_cutoff_hour', $validated) && $validated['day_cutoff_hour'] !== null) {
            $editor->forceFill(['day_cutoff_hour' => (int) $validated['day_cutoff_hour']]);
        }

        $editor->save();

        return $editor->refresh();
    }
}
