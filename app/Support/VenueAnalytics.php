<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;

/**
 * The venue's analytics read models, extracted from the analytics
 * dashboard so the web screen, the PDF/CSV exports and the API compute
 * the SAME numbers. Everything is bucketed on the venue's business day
 * (timezone + cutoff [F-22]) via BusinessDay, and cancelled orders are
 * excluded everywhere through Order::countable() [#12].
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
        $orders = Order::query()->countable()->where('editor_id', $venue->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->with(['items.product'])
            ->get();

        $orderCount = $orders->count();
        $totalSales = $orders->sum('total_amount');
        $averageOrderValue = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0;

        $productCounts = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pid = $item->product_id;
                $productCounts[$pid] = ($productCounts[$pid] ?? 0) + $item->quantity;
            }
        }
        arsort($productCounts);
        $topProductId = key($productCounts);
        $topProduct = $topProductId ? (Product::find($topProductId)->name ?? null) : null;

        $hourCounts = [];
        foreach ($orders as $order) {
            // Peak hour in the venue's own clock.
            $hour = BusinessDay::localHour($venue, $order->created_at);
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }
        arsort($hourCounts);
        $peakHour = key($hourCounts);

        return [
            'total_sales' => $totalSales,
            'order_count' => $orderCount,
            'top_product' => $topProduct,
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
        $orders = Order::query()->countable()->where('editor_id', $venue->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->with(['items.product'])
            ->get();

        $productStats = [];
        $categoryStats = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pid = $item->product_id;
                $cid = $item->product ? $item->product->category_id : null;
                $revenue = $item->quantity * $item->price;
                if (! isset($productStats[$pid])) {
                    $productStats[$pid] = [
                        'product_id' => $pid,
                        'name' => $item->product ? $item->product->name : 'Unknown',
                        'quantity' => 0,
                        'revenue' => 0,
                    ];
                }
                $productStats[$pid]['quantity'] += $item->quantity;
                $productStats[$pid]['revenue'] += $revenue;
                if ($cid) {
                    if (! isset($categoryStats[$cid])) {
                        $cat = Category::find($cid);
                        $categoryStats[$cid] = [
                            'category_id' => $cid,
                            'name' => $cat ? $cat->name : 'Unknown',
                            'quantity' => 0,
                            'revenue' => 0,
                        ];
                    }
                    $categoryStats[$cid]['quantity'] += $item->quantity;
                    $categoryStats[$cid]['revenue'] += $revenue;
                }
            }
        }

        return [
            'top_products' => collect($productStats)->sortByDesc('quantity')->take(5)->values()->all(),
            'least_products' => collect($productStats)->sortBy('quantity')->take(5)->values()->all(),
            'category_sales' => collect($categoryStats)->sortByDesc('revenue')->values()->all(),
            'category_orders' => collect($categoryStats)->sortByDesc('quantity')->values()->all(),
        ];
    }

    /**
     * Service operations for one range: session durations, turnover,
     * downtime, QR funnel and staff-vs-guest order attribution.
     */
    public static function serviceOps(User $venue, string $range): array
    {
        [$from, $to] = BusinessDay::rangeUtc($venue, $range);
        $sessions = TableSession::query()->where('editor_id', $venue->id)
            ->where('opened_at', '>=', $from)->where('opened_at', '<', $to)
            ->get();
        $orders = Order::query()->countable()->where('editor_id', $venue->id)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->with('table')
            ->get();
        $activities = ActivityLog::query()->where('editor_id', $venue->id)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->get();

        $tableCounts = $orders->groupBy('table_id')->map->count();
        $mostUsedTableId = $tableCounts->sortDesc()->keys()->first();
        $mostUsedTable = $mostUsedTableId ? (Table::find($mostUsedTableId)->table_number ?? $mostUsedTableId) : null;

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

        $qrScans = $activities->where('type', 'qr_scan')->count();
        $qrToOrder = $qrScans ? round($orders->count() / $qrScans * 100, 1) : null;

        $avgTimeQrToOrder = null;
        $qrTimes = [];
        foreach ($activities->where('type', 'qr_scan') as $scan) {
            $order = $orders->where('table_id', $scan->table_id)
                ->where('created_at', '>=', $scan->created_at)
                ->sortBy('created_at')
                ->first();
            if ($order) {
                $qrTimes[] = $order->created_at->diffInSeconds($scan->created_at);
            }
        }
        if (count($qrTimes)) {
            $avgTimeQrToOrder = round(array_sum($qrTimes) / count($qrTimes) / 60, 2);
        }

        // Manual orders carry created_by; guest QR orders are grouped as such.
        $staffOrderCountsArr = [];
        foreach ($orders->groupBy('created_by')->map->count() as $uid => $count) {
            $name = $uid ? (User::find($uid)->name ?? ('User #'.$uid)) : 'Guests (QR)';
            $staffOrderCountsArr[] = ['name' => $name, 'orders' => $count];
        }

        $tableUsageArr = [];
        foreach ($orders->groupBy('table_id')->map->count() as $tid => $count) {
            $table = $tid ? (Table::find($tid)->table_number ?? $tid) : 'Unknown';
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
     * The trailing 12 business months (venue timezone + cutoff).
     */
    public static function monthly(User $venue): array
    {
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            [$from, $to, $date] = BusinessDay::monthRangeUtc($venue, $i);
            $orders = Order::query()->countable()
                ->where('editor_id', $venue->id)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->with(['items.product'])
                ->get();

            $orderCount = $orders->count();
            $totalSales = $orders->sum('total_amount');
            $averageOrderValue = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0;

            $productCounts = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $pid = $item->product_id;
                    $productCounts[$pid] = ($productCounts[$pid] ?? 0) + $item->quantity;
                }
            }
            arsort($productCounts);
            $topProductId = key($productCounts);
            $topProduct = $topProductId ? (Product::find($topProductId)->name ?? null) : null;

            $hourCounts = [];
            foreach ($orders as $order) {
                $hour = BusinessDay::localHour($venue, $order->created_at);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
            arsort($hourCounts);
            $peakHour = key($hourCounts);

            $months[$date->year.'-'.$date->month] = [
                'label' => $date->format('F Y'),
                'total_sales' => $totalSales,
                'order_count' => $orderCount,
                'top_product' => $topProduct,
                'average_order_value' => $averageOrderValue,
                'peak_hour' => $peakHour,
            ];
        }

        return $months;
    }

    /**
     * Product units sold across the standard ranges, one row per product.
     */
    public static function productMatrix(User $venue): array
    {
        $allProductNames = collect();
        $productSales = [];
        foreach (['today', '7days', '30days'] as $key) {
            [$from, $to] = BusinessDay::rangeUtc($venue, $key);
            $orders = Order::countable()->where('editor_id', $venue->id)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->with('items.product')
                ->get();
            $stats = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $name = $item->product ? $item->product->name : 'Unknown';
                    $stats[$name] = ($stats[$name] ?? 0) + $item->quantity;
                    $allProductNames->push($name);
                }
            }
            $productSales[$key] = $stats;
        }

        $matrix = [];
        foreach ($allProductNames->unique()->sort()->values() as $name) {
            $matrix[$name] = [
                'today' => $productSales['today'][$name] ?? 0,
                '7days' => $productSales['7days'][$name] ?? 0,
                '30days' => $productSales['30days'][$name] ?? 0,
            ];
        }

        return $matrix;
    }
}
