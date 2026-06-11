<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Guest device identity for the QR approval flow [F-18].
 *
 * Approvals were keyed on IP alone: venue NAT can put the whole room
 * behind one address, and mobile CGNAT rotates a guest's IP mid-session.
 * A long random cookie identifies the device instead; the IP remains a
 * fallback for requests recorded before the cookie existed.
 */
class DeviceToken
{
    public const COOKIE = 'barmada_device';

    /** One year, in minutes. */
    private const LIFETIME = 525600;

    /**
     * The device token presented by this request, if any (sanitized).
     */
    public static function fromRequest(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        if (is_string($value) && preg_match('/^[A-Za-z0-9]{20,64}$/', $value)) {
            return $value;
        }

        return null;
    }

    /**
     * The request's device token, minting (and queueing) a new one when
     * absent. Call from cookie-capable (web group) routes only.
     */
    public static function ensure(Request $request): string
    {
        $token = self::fromRequest($request);

        if (! $token) {
            $token = Str::random(40);
            Cookie::queue(Cookie::make(
                self::COOKIE,
                $token,
                self::LIFETIME,
                '/',
                null,
                null,   // secure: follow session config default
                false,  // httpOnly false not required; JS never reads it, but
                        // keep it simple for the stateless POST path
                false,
                'Lax'
            ));
        }

        return $token;
    }
}
