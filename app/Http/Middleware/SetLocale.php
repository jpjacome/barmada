<?php

namespace App\Http\Middleware;

use App\Support\GuestLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the locale for the current request before it reaches any
 * controller.
 *
 * - Authenticated users (editor, staff, admin) get their venue's
 *   configured locale — editors their own, staff their editor's — via
 *   User::venueSettingsUser(). This is the "operator UI" locale.
 * - Everyone else (including guests with no venue in scope yet) falls
 *   back to the browser's Accept-Language, defaulting to Spanish.
 *
 * Guest controllers that DO have a table/venue in scope (qrEntry, the
 * order form, the session page, ...) call `$venue->guestLocale()`
 * themselves right after this middleware runs, which intentionally
 * overrides whatever this middleware picked — this middleware only
 * covers the routes/requests that have nothing more specific to go on.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        if ($user = Auth::user()) {
            $venueUser = $user->venueSettingsUser();
            $locale = $venueUser->locale ?: null;
        }

        if (! $locale) {
            $locale = GuestLocale::negotiate($request);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
