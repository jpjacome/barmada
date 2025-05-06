<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!($user && ($user->is_admin || $user->is_editor))) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this page.');
        }
        // You can add analytics logic here later
        return view('analytics');
    }

    // --- Analytics Backend Methods ---
    // [2025-05-04] Scaffolding analytics aggregation methods for dashboard metrics.
    // These methods will be implemented to provide real data for the analytics dashboard.

    /**
     * Get sales and revenue statistics for the dashboard.
     * @param string $range (e.g. 'today', '7days', '30days', 'month', 'custom')
     * @param int $editorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesAndRevenueStats(Request $request)
    {
        // [2025-05-04] Implements aggregation for today, last 7 days, last 30 days, and current month
        $user = auth()->user();
        $editorId = $request->input('editor_id', $user ? $user->id : null);
        $range = $request->input('range', 'today');
        $now = now();
        $query = \App\Models\Order::query()->where('editor_id', $editorId);

        // Date filtering
        switch ($range) {
            case 'today':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case '7days':
                $query->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
                break;
            case '30days':
                $query->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay());
                break;
            case 'month':
                $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                break;
            default:
                // fallback to today
                $query->whereDate('created_at', $now->toDateString());
        }

        $orders = $query->with(['items.product'])->get();
        $orderCount = $orders->count();
        $totalSales = $orders->sum('total_amount');
        $averageOrderValue = $orderCount > 0 ? round($totalSales / $orderCount, 2) : 0;

        // Top product by quantity
        $productCounts = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pid = $item->product_id;
                $productCounts[$pid] = ($productCounts[$pid] ?? 0) + $item->quantity;
            }
        }
        arsort($productCounts);
        $topProductId = key($productCounts);
        $topProduct = null;
        if ($topProductId) {
            $product = \App\Models\Product::find($topProductId);
            $topProduct = $product ? $product->name : null;
        }

        // Peak hour (hour with most orders)
        $hourCounts = [];
        foreach ($orders as $order) {
            $hour = $order->created_at->format('H:00');
            $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
        }
        arsort($hourCounts);
        $peakHour = key($hourCounts);

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'order_count' => $orderCount,
                'top_product' => $topProduct,
                'average_order_value' => $averageOrderValue,
                'peak_hour' => $peakHour,
            ]
        ]);
    }

    /**
     * Get product and category analytics for the dashboard.
     * @param string $range (e.g. 'today', '7days', '30days', 'month')
     * @param int $editorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductCategoryAnalytics(Request $request)
    {
        // [2025-05-04] Implements aggregation for product/category analytics
        $user = auth()->user();
        $editorId = $request->input('editor_id', $user ? $user->id : null);
        $range = $request->input('range', 'today');
        $now = now();
        $orderQuery = \App\Models\Order::query()->where('editor_id', $editorId);

        // Date filtering
        switch ($range) {
            case 'today':
                $orderQuery->whereDate('created_at', $now->toDateString());
                break;
            case '7days':
                $orderQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
                break;
            case '30days':
                $orderQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay());
                break;
            case 'month':
                $orderQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                break;
            default:
                $orderQuery->whereDate('created_at', $now->toDateString());
        }

        $orders = $orderQuery->with(['items.product'])->get();
        $productStats = [];
        $categoryStats = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pid = $item->product_id;
                $cid = $item->product ? $item->product->category_id : null;
                $revenue = $item->quantity * $item->price;
                // Product stats
                if (!isset($productStats[$pid])) {
                    $productStats[$pid] = [
                        'product_id' => $pid,
                        'name' => $item->product ? $item->product->name : 'Unknown',
                        'quantity' => 0,
                        'revenue' => 0,
                    ];
                }
                $productStats[$pid]['quantity'] += $item->quantity;
                $productStats[$pid]['revenue'] += $revenue;
                // Category stats
                if ($cid) {
                    if (!isset($categoryStats[$cid])) {
                        $cat = \App\Models\Category::find($cid);
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
        // Top/least selling products by quantity
        $topProducts = collect($productStats)->sortByDesc('quantity')->take(5)->values()->all();
        $leastProducts = collect($productStats)->sortBy('quantity')->take(5)->values()->all();
        // Category sales/order counts
        $categorySales = collect($categoryStats)->sortByDesc('revenue')->values()->all();
        $categoryOrders = collect($categoryStats)->sortByDesc('quantity')->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'top_products' => $topProducts,
                'least_products' => $leastProducts,
                'category_sales' => $categorySales,
                'category_orders' => $categoryOrders,
            ]
        ]);
    }

    /**
     * Get service and operations analytics for the dashboard.
     * @param string $range (e.g. 'today', '7days', '30days', 'month')
     * @param int $editorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceOperationsStats(Request $request)
    {
        // [2025-05-04] Implements aggregation for service/operations analytics
        $user = auth()->user();
        $editorId = $request->input('editor_id', $user ? $user->id : null);
        $range = $request->input('range', 'today');
        $now = now();
        $sessionQuery = \App\Models\TableSession::query()->where('editor_id', $editorId);
        $orderQuery = \App\Models\Order::query()->where('editor_id', $editorId);
        $activityQuery = \App\Models\ActivityLog::query()->where('editor_id', $editorId);

        // Date filtering
        switch ($range) {
            case 'today':
                $sessionQuery->whereDate('opened_at', $now->toDateString());
                $orderQuery->whereDate('created_at', $now->toDateString());
                $activityQuery->whereDate('created_at', $now->toDateString());
                break;
            case '7days':
                $sessionQuery->where('opened_at', '>=', $now->copy()->subDays(7)->startOfDay());
                $orderQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
                $activityQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay());
                break;
            case '30days':
                $sessionQuery->where('opened_at', '>=', $now->copy()->subDays(30)->startOfDay());
                $orderQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay());
                $activityQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay());
                break;
            case 'month':
                $sessionQuery->whereMonth('opened_at', $now->month)->whereYear('opened_at', $now->year);
                $orderQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                $activityQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                break;
            default:
                $sessionQuery->whereDate('opened_at', $now->toDateString());
                $orderQuery->whereDate('created_at', $now->toDateString());
                $activityQuery->whereDate('created_at', $now->toDateString());
        }

        $sessions = $sessionQuery->get();
        $orders = $orderQuery->with('table')->get();
        $activities = $activityQuery->get();

        // Most used table
        $tableCounts = $orders->groupBy('table_id')->map->count();
        $mostUsedTableId = $tableCounts->sortDesc()->keys()->first();
        $mostUsedTable = $mostUsedTableId ? (\App\Models\Table::find($mostUsedTableId)->table_number ?? $mostUsedTableId) : null;

        // Average session duration
        $durations = $sessions->map(function($s) {
            if ($s->opened_at && $s->closed_at) {
                return $s->closed_at->diffInMinutes($s->opened_at);
            }
            return null;
        })->filter();
        $avgSessionDuration = $durations->count() ? round($durations->avg(), 1) : null;

        // Sessions today
        $sessionsToday = $sessions->count();

        // Session reopenings
        $sessionReopenings = $sessions->where('status', 'reopened')->count();

        // Table turnover rate (sessions per table)
        $tableTurnover = $sessionsToday && $sessions->count() ? round($sessionsToday / $sessions->groupBy('table_id')->count(), 2) : null;

        // Downtime per table (average time between sessions)
        $downtimePerTable = null;
        $downtimeArr = [];
        foreach ($sessions->groupBy('table_id') as $tableSessions) {
            $sorted = $tableSessions->sortBy('opened_at')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted[$i-1];
                $curr = $sorted[$i];
                if ($prev->closed_at && $curr->opened_at) {
                    $downtimeArr[] = $curr->opened_at->diffInMinutes($prev->closed_at);
                }
            }
        }
        if (count($downtimeArr)) {
            $downtimePerTable = round(array_sum($downtimeArr) / count($downtimeArr), 1);
        }

        // QR scans (activity log type = 'qr_scan')
        $qrScans = $activities->where('type', 'qr_scan')->count();
        // QR to order conversion (orders created after qr_scan)
        $qrToOrder = $qrScans ? round($orders->count() / $qrScans * 100, 1) : null;
        // Avg. time QR to order (activity log: qr_scan -> order created)
        $avgTimeQrToOrder = null;
        $qrTimes = [];
        foreach ($activities->where('type', 'qr_scan') as $scan) {
            $order = $orders->where('table_id', $scan->table_id)->where('created_at', '>=', $scan->created_at)->sortBy('created_at')->first();
            if ($order) {
                $qrTimes[] = $order->created_at->diffInSeconds($scan->created_at);
            }
        }
        if (count($qrTimes)) {
            $avgTimeQrToOrder = round(array_sum($qrTimes) / count($qrTimes) / 60, 2); // in minutes
        }

        // Staff order counts (by user_id)
        $staffOrderCounts = $orders->groupBy('user_id')->map->count();
        $staffOrderCountsArr = [];
        foreach ($staffOrderCounts as $uid => $count) {
            $user = $uid ? (\App\Models\User::find($uid)->name ?? $uid) : 'Unknown';
            $staffOrderCountsArr[] = ['name' => $user, 'orders' => $count];
        }

        // Table usage distribution (for pie chart)
        $tableUsage = $orders->groupBy('table_id')->map->count();
        $tableUsageArr = [];
        foreach ($tableUsage as $tid => $count) {
            $table = $tid ? (\App\Models\Table::find($tid)->table_number ?? $tid) : 'Unknown';
            $tableUsageArr[] = ['table' => $table, 'orders' => $count];
        }

        return response()->json([
            'success' => true,
            'data' => [
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
            ]
        ]);
    }
}
