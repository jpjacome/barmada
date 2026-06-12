<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for the staff mobile app. One token per device, abilities
 * derived from the account's role flags; enforcement rides on the same
 * policies as the web.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:60',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken($validated['device_name'], $user->apiTokenAbilities());

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $user->apiTokenAbilities(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'abilities' => $request->user()->currentAccessToken()->abilities ?? [],
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    private function userPayload(User $user): array
    {
        $venue = $user->venueSettingsUser();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'is_editor' => (bool) $user->is_editor,
            'is_staff' => (bool) $user->is_staff,
            'editor_id' => $user->effectiveEditorId(),
            'venue' => [
                'business_name' => $venue->business_name,
                'currency_symbol' => $venue->currencySymbol(),
                'guest_locale' => $venue->guestLocale(),
                'timezone' => $venue->businessTimezone(),
                'day_cutoff_hour' => $venue->dayCutoffHour(),
            ],
        ];
    }
}
