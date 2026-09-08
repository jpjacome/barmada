<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\VenueAnalytics;
use Illuminate\Http\Request;

/**
 * The venue's analytics, computed by the SAME read models as the web
 * dashboard and exports (VenueAnalytics): business-day bucketing
 * (timezone + cutoff [F-22]), cancelled orders excluded [#12].
 *
 * Editors only — the web analytics route carries the same restriction.
 */
class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $venue = $this->venue($request);
        $range = $this->range($request);

        return response()->json([
            'range' => $range,
            'currency_symbol' => $venue->currencySymbol(),
            'summary' => VenueAnalytics::summary($venue, $range),
        ]);
    }

    public function products(Request $request)
    {
        $venue = $this->venue($request);
        $range = $this->range($request);

        return response()->json([
            'range' => $range,
            'products' => VenueAnalytics::productAndCategoryStats($venue, $range),
        ]);
    }

    public function serviceOps(Request $request)
    {
        $venue = $this->venue($request);
        $range = $this->range($request);

        return response()->json([
            'range' => $range,
            'service_ops' => VenueAnalytics::serviceOps($venue, $range),
        ]);
    }

    public function monthly(Request $request)
    {
        return response()->json([
            'months' => VenueAnalytics::monthly($this->venue($request)),
        ]);
    }

    public function productMatrix(Request $request)
    {
        return response()->json([
            'matrix' => VenueAnalytics::productMatrix($this->venue($request)),
        ]);
    }

    /** What guests paid with, and what is still owed, for one range. */
    public function paymentMix(Request $request)
    {
        $venue = $this->venue($request);
        $range = $this->range($request);

        return response()->json([
            'range' => $range,
            'currency_symbol' => $venue->currencySymbol(),
            'payment_mix' => VenueAnalytics::paymentMix($venue, $range),
        ]);
    }

    /** Who took the money: payments per actor for one range. */
    public function staff(Request $request)
    {
        $venue = $this->venue($request);
        $range = $this->range($request);

        return response()->json([
            'range' => $range,
            'currency_symbol' => $venue->currencySymbol(),
            'staff' => VenueAnalytics::staffAccountability($venue, $range),
        ]);
    }

    /**
     * The per-business-month figures an accountant needs for the IVA
     * return. months defaults to the trailing 12.
     */
    public function taxPeriods(Request $request)
    {
        $venue = $this->venue($request);
        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:36',
        ]);

        return response()->json([
            'currency_symbol' => $venue->currencySymbol(),
            'periods' => VenueAnalytics::taxPeriods($venue, (int) ($validated['months'] ?? 12)),
        ]);
    }

    private function venue(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->is_editor, 403);

        return $user;
    }

    private function range(Request $request): string
    {
        $validated = $request->validate([
            'range' => 'nullable|in:today,7days,30days,month',
        ]);

        return $validated['range'] ?? 'today';
    }
}
