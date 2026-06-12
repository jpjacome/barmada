<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Settings\UpdateBusinessSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Per-venue business settings (currency, guest language, timezone,
 * business-day cutoff). Editors only — staff inherit their venue's
 * settings through /auth/user. [F-9, F-10, F-22]
 */
class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->is_editor, 403);

        return response()->json(['settings' => $this->settingsPayload($user)]);
    }

    public function update(Request $request, UpdateBusinessSettings $updateSettings)
    {
        $user = $request->user();
        abort_unless($user && $user->is_editor, 403);

        $validated = $request->validate([
            'currency_symbol' => ['required', 'string', 'max:5', 'regex:/^[^<>"\']+$/u'],
            'locale' => ['required', 'in:en,es'],
            'business_timezone' => ['nullable', 'timezone:all'],
            'day_cutoff_hour' => ['nullable', 'integer', 'min:0', 'max:12'],
        ]);

        $updateSettings->handle($user, $validated);

        return response()->json(['settings' => $this->settingsPayload($user->refresh())]);
    }

    private function settingsPayload($user): array
    {
        return [
            'business_name' => $user->business_name,
            'username' => $user->username,
            'currency_symbol' => $user->currencySymbol(),
            'locale' => $user->guestLocale(),
            'business_timezone' => $user->businessTimezone(),
            'day_cutoff_hour' => $user->dayCutoffHour(),
        ];
    }
}
