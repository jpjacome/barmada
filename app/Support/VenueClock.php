<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Wall-clock time as the venue sees it.
 *
 * Every timestamp in the database is UTC. Analytics already converts
 * through BusinessDay, but the bill, the order ticket, the guest's
 * session page and the staff board printed raw UTC — a Quito venue's
 * receipt read 02:40 at a quarter to ten in the evening. Anything a
 * human reads goes through here.
 */
class VenueClock
{
    /**
     * The venue whose clock applies to $user: an editor is their own
     * venue, staff belong to one, admins and guests have none (UTC).
     */
    public static function venueFor(?User $user): ?User
    {
        if (! $user) {
            return null;
        }

        if ($user->is_editor) {
            return $user;
        }

        if ($user->is_staff && $user->editor_id) {
            return User::withoutGlobalScopes()->find($user->editor_id);
        }

        return null;
    }

    public static function timezone(?User $venue): string
    {
        return $venue?->businessTimezone() ?: 'UTC';
    }

    /**
     * $moment converted to the venue's timezone. Accepts anything Carbon
     * can parse; null yields null so templates can chain safely.
     */
    public static function at(?User $venue, DateTimeInterface|string|null $moment): ?Carbon
    {
        if ($moment === null || $moment === '') {
            return null;
        }

        $carbon = $moment instanceof CarbonInterface
            ? Carbon::instance($moment)
            : Carbon::parse($moment);

        return $carbon->copy()->setTimezone(self::timezone($venue));
    }

    /**
     * Formatted venue-local time, or an empty string for a null moment.
     */
    public static function format(?User $venue, DateTimeInterface|string|null $moment, string $format = 'H:i'): string
    {
        return self::at($venue, $moment)?->format($format) ?? '';
    }

    /**
     * The venue's "now", for stamping documents produced on demand.
     */
    public static function now(?User $venue): Carbon
    {
        return now()->setTimezone(self::timezone($venue));
    }
}
