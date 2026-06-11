<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

/**
 * Business-day arithmetic for venue analytics [F-22].
 *
 * A venue's "day" starts at its configured cutoff hour in its own
 * timezone (e.g. 06:00 in America/Guayaquil), not at UTC midnight — a
 * bar's Friday night includes the small hours of Saturday. All helpers
 * return UTC instants so they can be compared directly against the
 * UTC `created_at` / `opened_at` columns.
 *
 * Defaults (UTC, cutoff 0) reproduce the previous calendar-day behaviour.
 */
class BusinessDay
{
    /**
     * Start of the business day containing $moment, as a UTC instant.
     */
    public static function dayStartUtc(User $venue, ?Carbon $moment = null): Carbon
    {
        $moment = $moment ?: now();
        $local = $moment->copy()->setTimezone($venue->businessTimezone());
        $start = $local->copy()->startOfDay()->addHours($venue->dayCutoffHour());

        if ($local->lt($start)) {
            $start->subDay();
        }

        return $start->setTimezone('UTC');
    }

    /**
     * [start, end) UTC range for an analytics range keyword.
     * 'today' = current business day so far; '7days'/'30days' = the last
     * N business days including today; 'month' = business month to date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rangeUtc(User $venue, string $range, ?Carbon $now = null): array
    {
        $now = $now ?: now();
        $todayStart = self::dayStartUtc($venue, $now);

        return match ($range) {
            '7days' => [$todayStart->copy()->subDays(6), $now->copy()],
            '30days' => [$todayStart->copy()->subDays(29), $now->copy()],
            'month' => [self::monthStartUtc($venue, $now), $now->copy()],
            default => [$todayStart, $now->copy()], // 'today'
        };
    }

    /**
     * Start (UTC) of the business month containing $moment: the cutoff
     * hour on the 1st, venue-local.
     */
    public static function monthStartUtc(User $venue, Carbon $moment): Carbon
    {
        return $moment->copy()
            ->setTimezone($venue->businessTimezone())
            ->startOfMonth()
            ->addHours($venue->dayCutoffHour())
            ->setTimezone('UTC');
    }

    /**
     * [start, end) UTC range of the business month that lies $monthsAgo
     * months before now (0 = current month).
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon} start, end, local month reference
     */
    public static function monthRangeUtc(User $venue, int $monthsAgo, ?Carbon $now = null): array
    {
        $now = $now ?: now();
        $localRef = $now->copy()->setTimezone($venue->businessTimezone())->subMonthsNoOverflow($monthsAgo);
        $start = $localRef->copy()->startOfMonth()->addHours($venue->dayCutoffHour());
        $end = $localRef->copy()->startOfMonth()->addMonthNoOverflow()->addHours($venue->dayCutoffHour());

        return [$start->copy()->setTimezone('UTC'), $end->copy()->setTimezone('UTC'), $localRef];
    }

    /**
     * [start, end) UTC range of the business day that lies $daysAgo days
     * before now (0 = today). Used by per-day chart loops.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon} start, end, local day reference
     */
    public static function dayRangeUtc(User $venue, int $daysAgo, ?Carbon $now = null): array
    {
        $now = $now ?: now();
        $todayStart = self::dayStartUtc($venue, $now);
        $start = $todayStart->copy()->subDays($daysAgo);
        $end = $start->copy()->addDay();
        $localRef = $start->copy()->setTimezone($venue->businessTimezone());

        return [$start, $end, $localRef];
    }

    /**
     * Venue-local hour bucket ("21:00") for a UTC instant — peak hours
     * should read in the bar's own clock.
     */
    public static function localHour(User $venue, Carbon $utcMoment): string
    {
        return $utcMoment->copy()->setTimezone($venue->businessTimezone())->format('H:00');
    }
}
