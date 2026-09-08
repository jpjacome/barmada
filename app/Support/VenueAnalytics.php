<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The venue's analytics read models. The web dashboard, the PDF/CSV
 * exports and the API all call these methods and nothing else, so they
 * cannot drift apart: there is one implementation of every number.
 *
 * Everything is bucketed on the venue's business day (timezone + cutoff
 * [F-22]) via BusinessDay, and cancelled orders are excluded everywhere
 * through Order::countable() [#12].
 *
 * Aggregation happens in SQL. No method hydrates a model per order or
 * per item, and none of them looks a product/table/user up inside a
 * loop; the only rows that ever cross into PHP are grouped results and,
 * where a timezone conversion makes SQL the wrong tool, bare scalars:
 *
 *  - hour-of-day buckets must be read in the venue's own clock, and a
 *    venue can sit on a DST or half-hour offset, so order timestamps are
 *    fetched as strings (one query, no models) and converted with
 *    BusinessDay::localHour.
 *  - session durations and QR-to-order latency are Carbon differences
 *    whose exact semantics are load-bearing, so sessions are fetched as
 *    plain rows (one query) and diffed in PHP.
 *
 * Business-month buckets are pushed into SQL as a CASE over the UTC
 * boundaries BusinessDay computes, which keeps monthly() at a couple of
 * queries instead of one per month, and stays portable between SQLite
 * (tests) and MySQL (production).
 *
 * $venue is the editor account that owns the tenant.
 */
class VenueAnalytics
{
    /**
     * Sales, order count, AOV, top product, peak hour (venue clock) and
     * the per-hour order distribution for one range.
     */
    public static function summary(User $venue, string $range): array
    {
        [$from, $to] = BusinessDay::rangeUtc($venue, $range);

        $totals = self::ordersIn($venue, $from, $to)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(orders.total_amount), 0) as total_sales')
            ->toBase()->first();

        $orderCount = (int) $totals->order_count;
        $totalSales = self::money($totals->total_sales);
        $averageOrderValue = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0;

        $top = self::ordersIn($venue, $from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw(
                'MAX(products.name) as name, SUM(order_items.quantity) as quantity'
                .', MIN(orders.created_at) as first_seen_at, MIN(orders.id) as first_order_id, MIN(order_items.id) as first_item_id'
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')->orderBy('first_seen_at')->orderBy('first_order_id')->orderBy('first_item_id')
            ->limit(1)
            ->toBase()->first();

        $hourCounts = self::hourDistribution($venue, self::orderTimestamps($venue, $from, $to));
        $peakHour = array_key_first($hourCounts);

        return [
            'total_sales' => $totalSales,
            'order_count' => $orderCount,
            'top_product' => $top->name ?? null,
            'average_order_value' => $averageOrderValue,
            'peak_hour' => $peakHour,
            'hour_distribution' => $hourCounts,
        ];
    }

    /**
     * Top/least sellers and per-category sales & volumes for one range.
     */
    public static function productAndCategoryStats(User $venue, string $range): array
    {
        [$from, $to] = BusinessDay::rangeUtc($venue, $range);

        $products = self::ordersIn($venue, $from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw(
                'order_items.product_id as product_id, MAX(products.name) as name'
                .', SUM(order_items.quantity) as quantity'
                .', SUM(order_items.quantity * order_items.price) as revenue'
                .', MIN(orders.created_at) as first_seen_at, MIN(orders.id) as first_order_id, MIN(order_items.id) as first_item_id'
            )
            ->groupBy('order_items.product_id')
            // First-appearance order: the tie-break the PHP implementation
            // inherited from iterating orders, then items, in id order.
            ->orderBy('first_seen_at')->orderBy('first_order_id')->orderBy('first_item_id')
            ->toBase()->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name ?? 'Unknown',
                'quantity' => (int) $row->quantity,
                'revenue' => self::money($row->revenue),
            ]);

        $categories = self::ordersIn($venue, $from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereNotNull('products.category_id')
            ->selectRaw(
                'products.category_id as category_id, MAX(categories.name) as name'
                .', SUM(order_items.quantity) as quantity'
                .', SUM(order_items.quantity * order_items.price) as revenue'
                .', MIN(orders.created_at) as first_seen_at, MIN(orders.id) as first_order_id, MIN(order_items.id) as first_item_id'
            )
            ->groupBy('products.category_id')
            ->orderBy('first_seen_at')->orderBy('first_order_id')->orderBy('first_item_id')
            ->toBase()->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'name' => $row->name ?? 'Unknown',
                'quantity' => (int) $row->quantity,
                'revenue' => self::money($row->revenue),
            ]);

        return [
            'top_products' => $products->sortByDesc('quantity')->take(5)->values()->all(),
            'least_products' => $products->sortBy('quantity')->take(5)->values()->all(),
            'category_sales' => $categories->sortByDesc('revenue')->values()->all(),
            'category_orders' => $categories->sortByDesc('quantity')->values()->all(),
        ];
    }

    /**
     * Service operations for one range: session durations, turnover,
     * downtime, QR funnel and staff-vs-guest order attribution.
     */
    public static function serviceOps(User $venue, string $range): array
    {
        [$from, $to] = BusinessDay::rangeUtc($venue, $range);

        $sessions = self::sessionRows($venue, $from, $to);
        $orders = self::serviceOrderRows($venue, $from, $to);
        $scans = self::qrScanRows($venue, $from, $to);

        $tableCounts = $orders->groupBy('table_id')->map->count();
        $mostUsedTableId = $tableCounts->sortDesc()->keys()->first();

        $tableNumbers = self::tableNumbers($tableCounts->keys());
        $mostUsedTable = $mostUsedTableId ? ($tableNumbers[$mostUsedTableId] ?? $mostUsedTableId) : null;

        $durations = $sessions->map(function ($s) {
            if ($s->opened_at && $s->closed_at) {
                return $s->closed_at->diffInMinutes($s->opened_at);
            }

            return null;
        })->filter();
        $avgSessionDuration = $durations->count() ? round($durations->avg(), 1) : null;

        $sessionsToday = $sessions->count();
        $sessionReopenings = $sessions->where('status', 'reopened')->count();
        $tableTurnover = $sessionsToday && $sessions->count()
            ? round($sessionsToday / $sessions->groupBy('table_id')->count(), 2)
            : null;

        $downtimePerTable = null;
        $downtimeArr = [];
        foreach ($sessions->groupBy('table_id') as $tableSessions) {
            $sorted = $tableSessions->sortBy('opened_at')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted[$i - 1];
                $curr = $sorted[$i];
                if ($prev->closed_at && $curr->opened_at) {
                    $downtimeArr[] = $curr->opened_at->diffInMinutes($prev->closed_at);
                }
            }
        }
        if (count($downtimeArr)) {
            $downtimePerTable = round(array_sum($downtimeArr) / count($downtimeArr), 1);
        }

        $qrScans = $scans->count();
        $qrToOrder = $qrScans ? round($orders->count() / $qrScans * 100, 1) : null;

        $avgTimeQrToOrder = null;
        $qrTimes = [];
        // Orders per table, chronologically, so each scan's first
        // following order is a binary search instead of a rescan.
        $ordersByTable = $orders->groupBy('table_id')->map(fn ($g) => $g->sortBy('created_at')->values());
        foreach ($scans as $scan) {
            $candidates = $ordersByTable->get($scan->table_id);
            $order = $candidates ? self::firstAtOrAfter($candidates, $scan->created_at) : null;
            if ($order) {
                $qrTimes[] = $order->created_at->diffInSeconds($scan->created_at);
            }
        }
        if (count($qrTimes)) {
            $avgTimeQrToOrder = round(array_sum($qrTimes) / count($qrTimes) / 60, 2);
        }

        // Manual orders carry created_by; guest QR orders are grouped as such.
        $staffCounts = $orders->groupBy('created_by')->map->count();
        $staffNames = self::userNames($staffCounts->keys());
        $staffOrderCountsArr = [];
        foreach ($staffCounts as $uid => $count) {
            $name = $uid ? ($staffNames[$uid] ?? ('User #'.$uid)) : 'Guests (QR)';
            $staffOrderCountsArr[] = ['name' => $name, 'orders' => $count];
        }

        $tableUsageArr = [];
        foreach ($tableCounts as $tid => $count) {
            $table = $tid ? ($tableNumbers[$tid] ?? $tid) : 'Unknown';
            $tableUsageArr[] = ['table' => $table, 'orders' => $count];
        }

        return [
            'most_used_table' => $mostUsedTable,
            'avg_session_duration' => $avgSessionDuration,
            'sessions_today' => $sessionsToday,
            'session_reopenings' => $sessionReopenings,
            'table_turnover' => $tableTurnover,
            'downtime_per_table' => $downtimePerTable,
            'qr_scans' => $qrScans,
            'qr_to_order_conversion' => $qrToOrder,
            'avg_time_qr_to_order' => $avgTimeQrToOrder,
            'staff_order_counts' => $staffOrderCountsArr,
            'table_usage_distribution' => $tableUsageArr,
        ];
    }

    /**
     * The trailing 12 business months (venue timezone + cutoff), in two
     * queries: one bucketed pass over the orders, one over their items.
     */
    public static function monthly(User $venue): array
    {
        $buckets = self::monthBuckets($venue, 12);
        [$spanFrom, $spanTo] = self::span($buckets);

        $perMonth = [];
        foreach ($buckets as $i => $bucket) {
            $perMonth[$i] = ['sales' => 0.0, 'orders' => 0, 'hours' => []];
        }

        $rows = self::ordersIn($venue, $spanFrom, $spanTo)
            ->selectRaw(self::bucketCase($buckets).' as bucket, orders.created_at as created_at, orders.total_amount as total_amount', self::bucketBindings($buckets))
            ->orderBy('orders.created_at')->orderBy('orders.id')
            ->toBase()->get();

        foreach ($rows as $row) {
            $i = (int) $row->bucket;
            if ($i < 0) {
                continue;
            }
            $perMonth[$i]['orders']++;
            $perMonth[$i]['sales'] += (float) $row->total_amount;
            $hour = BusinessDay::localHour($venue, Carbon::parse($row->created_at));
            $perMonth[$i]['hours'][$hour] = ($perMonth[$i]['hours'][$hour] ?? 0) + 1;
        }

        $topPerMonth = self::topProductPerBucket($venue, $buckets, $spanFrom, $spanTo);

        $months = [];
        foreach ($buckets as $i => $bucket) {
            $date = $bucket['ref'];
            $orderCount = $perMonth[$i]['orders'];
            $totalSales = round($perMonth[$i]['sales'], 2);
            $hours = $perMonth[$i]['hours'];
            arsort($hours);

            $months[$date->year.'-'.$date->month] = [
                'label' => $date->format('F Y'),
                'total_sales' => $totalSales,
                'order_count' => $orderCount,
                'top_product' => $topPerMonth[$i] ?? null,
                'average_order_value' => $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0,
                'peak_hour' => array_key_first($hours),
            ];
        }

        return $months;
    }

    /**
     * Product units sold across the standard ranges, one row per product
     * name. Today ⊂ 7 days ⊂ 30 days, so one pass over the widest window
     * with conditional sums answers all three.
     */
    public static function productMatrix(User $venue): array
    {
        [$todayFrom] = BusinessDay::rangeUtc($venue, 'today');
        [$weekFrom] = BusinessDay::rangeUtc($venue, '7days');
        [$monthFrom, $to] = BusinessDay::rangeUtc($venue, '30days');

        $rows = self::ordersIn($venue, $monthFrom, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw(
                "COALESCE(products.name, 'Unknown') as name"
                .', SUM(CASE WHEN orders.created_at >= ? THEN order_items.quantity ELSE 0 END) as today_qty'
                .', SUM(CASE WHEN orders.created_at >= ? THEN order_items.quantity ELSE 0 END) as week_qty'
                .', SUM(order_items.quantity) as month_qty',
                [self::bind($todayFrom), self::bind($weekFrom)]
            )
            ->groupBy('name')
            ->toBase()->get()
            ->keyBy('name');

        $matrix = [];
        foreach ($rows->keys()->unique()->sort()->values() as $name) {
            $matrix[$name] = [
                'today' => (int) $rows[$name]->today_qty,
                '7days' => (int) $rows[$name]->week_qty,
                '30days' => (int) $rows[$name]->month_qty,
            ];
        }

        return $matrix;
    }

    // --- Internals -----------------------------------------------------

    /**
     * Countable orders for one tenant in one UTC window, with every
     * column qualified so the query survives being joined to items,
     * products and categories.
     */
    private static function ordersIn(User $venue, Carbon $from, Carbon $to): Builder
    {
        return Order::query()->countable()
            ->where('orders.editor_id', $venue->id)
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<', $to);
    }

    /** Order timestamps only — no models — in insertion order. */
    private static function orderTimestamps(User $venue, Carbon $from, Carbon $to): Collection
    {
        return self::ordersIn($venue, $from, $to)
            ->orderBy('orders.created_at')->orderBy('orders.id')
            ->toBase()->pluck('orders.created_at');
    }

    /**
     * Orders per venue-local hour ("21:00"), descending by volume — the
     * order the dashboard and the exports have always rendered.
     */
    private static function hourDistribution(User $venue, Collection $timestamps): array
    {
        $hourCounts = [];
        foreach ($timestamps as $timestamp) {
            $hour = BusinessDay::localHour($venue, Carbon::parse($timestamp));
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }
        arsort($hourCounts);

        return $hourCounts;
    }

    /** Sessions as plain rows with Carbon timestamps, one query. */
    private static function sessionRows(User $venue, Carbon $from, Carbon $to): Collection
    {
        return TableSession::query()
            ->where('table_sessions.editor_id', $venue->id)
            ->where('table_sessions.opened_at', '>=', $from)
            ->where('table_sessions.opened_at', '<', $to)
            ->orderBy('table_sessions.opened_at')->orderBy('table_sessions.id')
            ->toBase()->get(['table_sessions.table_id', 'table_sessions.status', 'table_sessions.opened_at', 'table_sessions.closed_at'])
            ->map(fn ($row) => (object) [
                'table_id' => $row->table_id,
                'status' => $row->status,
                'opened_at' => $row->opened_at ? Carbon::parse($row->opened_at) : null,
                'closed_at' => $row->closed_at ? Carbon::parse($row->closed_at) : null,
            ]);
    }

    /** The order columns service-ops arithmetic needs, one query. */
    private static function serviceOrderRows(User $venue, Carbon $from, Carbon $to): Collection
    {
        return self::ordersIn($venue, $from, $to)
            ->orderBy('orders.created_at')->orderBy('orders.id')
            ->toBase()->get(['orders.table_id', 'orders.created_by', 'orders.created_at'])
            ->map(fn ($row) => (object) [
                'table_id' => $row->table_id,
                'created_by' => $row->created_by,
                'created_at' => Carbon::parse($row->created_at),
            ]);
    }

    private static function qrScanRows(User $venue, Carbon $from, Carbon $to): Collection
    {
        return ActivityLog::query()
            ->where('activity_logs.editor_id', $venue->id)
            ->where('activity_logs.type', 'qr_scan')
            ->where('activity_logs.created_at', '>=', $from)
            ->where('activity_logs.created_at', '<', $to)
            ->orderBy('activity_logs.created_at')->orderBy('activity_logs.id')
            ->toBase()->get(['activity_logs.table_id', 'activity_logs.created_at'])
            ->map(fn ($row) => (object) [
                'table_id' => $row->table_id,
                'created_at' => Carbon::parse($row->created_at),
            ]);
    }

    /**
     * The first order at or after $moment in a table's chronological
     * list. Binary search: the QR funnel used to rescan every order for
     * every scan.
     */
    private static function firstAtOrAfter(Collection $orders, Carbon $moment): ?object
    {
        $low = 0;
        $high = $orders->count() - 1;
        $found = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($orders[$mid]->created_at->greaterThanOrEqualTo($moment)) {
                $found = $orders[$mid];
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return $found;
    }

    /**
     * table_number keyed by table id. Tenant-scoped exactly as the old
     * Table::find() lookups were, but one query for all of them.
     *
     * @param  Collection<int, mixed>  $ids
     */
    private static function tableNumbers(Collection $ids): array
    {
        $ids = $ids->filter()->all();

        return $ids ? Table::query()->whereIn('tables.id', $ids)->pluck('table_number', 'id')->all() : [];
    }

    /** @param  Collection<int, mixed>  $ids */
    private static function userNames(Collection $ids): array
    {
        $ids = $ids->filter()->all();

        return $ids ? User::query()->whereIn('users.id', $ids)->pluck('name', 'id')->all() : [];
    }

    /**
     * The trailing $count business months, newest first.
     *
     * @return array<int, array{from: Carbon, to: Carbon, ref: Carbon}>
     */
    private static function monthBuckets(User $venue, int $count): array
    {
        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            [$from, $to, $ref] = BusinessDay::monthRangeUtc($venue, $i);
            $buckets[$i] = ['from' => $from, 'to' => $to, 'ref' => $ref];
        }

        return $buckets;
    }

    /**
     * @param  array<int, array{from: Carbon, to: Carbon, ref: Carbon}>  $buckets
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function span(array $buckets): array
    {
        $from = collect($buckets)->pluck('from')->sort()->first();
        $to = collect($buckets)->pluck('to')->sort()->last();

        return [$from, $to];
    }

    /**
     * A portable bucket index over business months: SQL cannot apply the
     * venue's cutoff itself, but the UTC boundaries are already known, so
     * a CASE over them puts every order in its month in one pass.
     *
     * @param  array<int, array{from: Carbon, to: Carbon, ref: Carbon}>  $buckets
     */
    private static function bucketCase(array $buckets): string
    {
        $sql = 'CASE';
        foreach (array_keys($buckets) as $i) {
            $sql .= ' WHEN orders.created_at >= ? AND orders.created_at < ? THEN '.(int) $i;
        }

        return $sql.' ELSE -1 END';
    }

    /**
     * @param  array<int, array{from: Carbon, to: Carbon, ref: Carbon}>  $buckets
     * @return array<int, string>
     */
    private static function bucketBindings(array $buckets): array
    {
        $bindings = [];
        foreach ($buckets as $bucket) {
            $bindings[] = self::bind($bucket['from']);
            $bindings[] = self::bind($bucket['to']);
        }

        return $bindings;
    }

    /**
     * Best-selling product name per month bucket, one query.
     *
     * @param  array<int, array{from: Carbon, to: Carbon, ref: Carbon}>  $buckets
     * @return array<int, string|null>
     */
    private static function topProductPerBucket(User $venue, array $buckets, Carbon $spanFrom, Carbon $spanTo): array
    {
        $rows = self::ordersIn($venue, $spanFrom, $spanTo)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw(
                self::bucketCase($buckets).' as bucket, MAX(products.name) as name'
                .', SUM(order_items.quantity) as quantity'
                .', MIN(orders.created_at) as first_seen_at, MIN(orders.id) as first_order_id, MIN(order_items.id) as first_item_id',
                self::bucketBindings($buckets)
            )
            ->groupByRaw('bucket, order_items.product_id')
            ->orderByRaw('bucket asc, quantity desc, first_seen_at asc, first_order_id asc, first_item_id asc')
            ->toBase()->get();

        $top = [];
        foreach ($rows as $row) {
            $i = (int) $row->bucket;
            if ($i < 0 || array_key_exists($i, $top)) {
                continue;
            }
            $top[$i] = $row->name;
        }

        return $top;
    }

    /** Bind a UTC instant the way the query grammar stores timestamps. */
    private static function bind(Carbon $moment): string
    {
        return $moment->copy()->utc()->format('Y-m-d H:i:s');
    }

    /** SUM() arrives as a float under SQLite and a string under MySQL. */
    private static function money($value): float
    {
        return round((float) $value, 2);
    }
}
