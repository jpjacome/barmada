<?php

namespace App\Http\Controllers;

use App\Support\BusinessDay;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AnalyticsPdfController extends Controller
{
    public function export(Request $request)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $currentMonthKey = $now->format('Y-n');
        $prevMonth = $now->copy()->subMonth();
        $prevMonthKey = $prevMonth->format('Y-n');

        // --- Monthly Stats ---
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            // Business months in the venue's timezone + cutoff. [F-22]
            [$mFrom, $mTo, $date] = BusinessDay::monthRangeUtc(auth()->user(), $i);
            $month = $date->month;
            $year = $date->year;
            $query = \App\Models\Order::query()->countable()
                ->where('editor_id', $editorId)
                ->where('created_at', '>=', $mFrom)
                ->where('created_at', '<', $mTo);
            $orders = $query->with(['items.product'])->get();
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
            $topProduct = $topProductId ? (\App\Models\Product::find($topProductId)->name ?? null) : null;
            $hourCounts = [];
            foreach ($orders as $order) {
                $hour = BusinessDay::localHour(auth()->user(), $order->created_at);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
            arsort($hourCounts);
            $peakHour = key($hourCounts);
            $months[$year.'-'.$month] = [
                'label' => $date->format('F Y'),
                'total_sales' => $totalSales,
                'order_count' => $orderCount,
                'top_product' => $topProduct,
                'average_order_value' => $averageOrderValue,
                'peak_hour' => $peakHour,
            ];
        }
        $monthlyStats = $months;

        // --- Product Sales Matrix ---
        $periods = [
            'today' => ['label' => 'Last Day', 'days' => 1],
            '7days' => ['label' => 'Last 7 Days', 'days' => 7],
            '30days' => ['label' => 'Last 30 Days', 'days' => 30],
        ];
        $allProductNames = collect();
        $productSales = [];
        foreach ($periods as $key => $period) {
            [$pFrom, $pTo] = BusinessDay::rangeUtc(auth()->user(), $key);
            $orders = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $pFrom)
                ->where('created_at', '<', $pTo)
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
        $allProductNames = $allProductNames->unique()->sort()->values();
        $matrix = [];
        foreach ($allProductNames as $name) {
            $matrix[$name] = [
                'today' => $productSales['today'][$name] ?? 0,
                '7days' => $productSales['7days'][$name] ?? 0,
                '30days' => $productSales['30days'][$name] ?? 0,
            ];
        }
        $productSalesMatrix = $matrix;

        // --- Service & Operations Stats (month/today only for PDF) ---
        $serviceOpsStats = [
            'month' => $this->aggregateServiceOpsStats('month', $editorId),
            'today' => $this->aggregateServiceOpsStats('today', $editorId),
        ];

        // --- Product Category Stats (month only for PDF) ---
        $productCategoryStats = [
            'month' => $this->aggregateProductCategoryStats('month', $editorId),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('analytics-dashboard-pdf', [
            'now' => $now,
            'currentMonthKey' => $currentMonthKey,
            'prevMonth' => $prevMonth,
            'prevMonthKey' => $prevMonthKey,
            'monthlyStats' => $monthlyStats,
            'productSalesMatrix' => $productSalesMatrix,
            'serviceOpsStats' => $serviceOpsStats,
            'productCategoryStats' => $productCategoryStats,
            'currency' => $user ? $user->currencySymbol() : '$',
        ]);
        return $pdf->download('analytics-dashboard.pdf');
    }

    public function exportWithCharts(Request $request)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $currentMonthKey = $now->format('Y-n');
        $prevMonth = $now->copy()->subMonth();
        $prevMonthKey = $prevMonth->format('Y-n');

        // --- Monthly Stats ---
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            // Business months in the venue's timezone + cutoff. [F-22]
            [$mFrom, $mTo, $date] = BusinessDay::monthRangeUtc(auth()->user(), $i);
            $month = $date->month;
            $year = $date->year;
            $query = \App\Models\Order::query()->countable()
                ->where('editor_id', $editorId)
                ->where('created_at', '>=', $mFrom)
                ->where('created_at', '<', $mTo);
            $orders = $query->with(['items.product'])->get();
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
            $topProduct = $topProductId ? (\App\Models\Product::find($topProductId)->name ?? null) : null;
            $hourCounts = [];
            foreach ($orders as $order) {
                $hour = BusinessDay::localHour(auth()->user(), $order->created_at);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
            arsort($hourCounts);
            $peakHour = key($hourCounts);
            $months[$year.'-'.$month] = [
                'label' => $date->format('F Y'),
                'total_sales' => $totalSales,
                'order_count' => $orderCount,
                'top_product' => $topProduct,
                'average_order_value' => $averageOrderValue,
                'peak_hour' => $peakHour,
            ];
        }
        $monthlyStats = $months;

        // --- Product Sales Matrix ---
        $periods = [
            'today' => ['label' => 'Last Day', 'days' => 1],
            '7days' => ['label' => 'Last 7 Days', 'days' => 7],
            '30days' => ['label' => 'Last 30 Days', 'days' => 30],
        ];
        $allProductNames = collect();
        $productSales = [];
        foreach ($periods as $key => $period) {
            [$pFrom, $pTo] = BusinessDay::rangeUtc(auth()->user(), $key);
            $orders = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $pFrom)
                ->where('created_at', '<', $pTo)
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
        $allProductNames = $allProductNames->unique()->sort()->values();
        $matrix = [];
        foreach ($allProductNames as $name) {
            $matrix[$name] = [
                'today' => $productSales['today'][$name] ?? 0,
                '7days' => $productSales['7days'][$name] ?? 0,
                '30days' => $productSales['30days'][$name] ?? 0,
            ];
        }
        $productSalesMatrix = $matrix;

        // --- Service & Operations Stats (month/today only for PDF) ---
        $serviceOpsStats = [
            'month' => $this->aggregateServiceOpsStats('month', $editorId),
            'today' => $this->aggregateServiceOpsStats('today', $editorId),
        ];

        // --- Product Category Stats (month only for PDF) ---
        $productCategoryStats = [
            'month' => $this->aggregateProductCategoryStats('month', $editorId),
        ];

        $chartImages = [
            'sales_chart' => $request->input('sales_chart'),
            'sales_last_week_chart' => $request->input('sales_last_week_chart'),
            'sales_last_month_chart' => $request->input('sales_last_month_chart'),
            'product_bar_chart' => $request->input('product_bar_chart'),
            'category_doughnut_chart' => $request->input('category_doughnut_chart'),
            'category_revenue_doughnut_chart' => $request->input('category_revenue_doughnut_chart'),
            'table_pie_chart' => $request->input('table_pie_chart'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('analytics-dashboard-pdf', [
            'now' => $now,
            'currentMonthKey' => $currentMonthKey,
            'prevMonth' => $prevMonth,
            'prevMonthKey' => $prevMonthKey,
            'monthlyStats' => $monthlyStats,
            'productSalesMatrix' => $productSalesMatrix,
            'serviceOpsStats' => $serviceOpsStats,
            'productCategoryStats' => $productCategoryStats,
            'chartImages' => $chartImages,
            'currency' => $user ? $user->currencySymbol() : '$',
        ]);
        return $pdf->download('analytics-dashboard.pdf');
    }

    public function exportCsv(Request $request)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $currentMonthKey = $now->format('Y-n');
        $prevMonth = $now->copy()->subMonth();
        $prevMonthKey = $prevMonth->format('Y-n');

        // Gather all analytics data as in the dashboard
        // --- Monthly Stats ---
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            // Business months in the venue's timezone + cutoff. [F-22]
            [$mFrom, $mTo, $date] = BusinessDay::monthRangeUtc(auth()->user(), $i);
            $month = $date->month;
            $year = $date->year;
            $query = \App\Models\Order::query()->countable()
                ->where('editor_id', $editorId)
                ->where('created_at', '>=', $mFrom)
                ->where('created_at', '<', $mTo);
            $orders = $query->with(['items.product'])->get();
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
            $topProduct = $topProductId ? (\App\Models\Product::find($topProductId)->name ?? null) : null;
            $hourCounts = [];
            foreach ($orders as $order) {
                $hour = BusinessDay::localHour(auth()->user(), $order->created_at);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
            arsort($hourCounts);
            $peakHour = key($hourCounts);
            $months[] = [
                'Month' => $date->format('F Y'),
                'Total Sales' => $totalSales,
                'Order Count' => $orderCount,
                'Top Product' => $topProduct,
                'Average Order Value' => $averageOrderValue,
                'Peak Hour' => $peakHour,
            ];
        }

        // --- Stats for Today, 7days, 30days, month ---
        $ranges = ['today', '7days', '30days', 'month'];
        $statsRows = [];
        foreach ($ranges as $range) {
            [$rFrom, $rTo] = BusinessDay::rangeUtc(auth()->user(), $range);
            $query = \App\Models\Order::query()->countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $rFrom)
                ->where('created_at', '<', $rTo);
            $orders = $query->with(['items.product'])->get();
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
            $topProduct = $topProductId ? (\App\Models\Product::find($topProductId)->name ?? null) : null;
            $hourCounts = [];
            foreach ($orders as $order) {
                $hour = BusinessDay::localHour(auth()->user(), $order->created_at);
                $hourCounts[$hour] = ($hourCounts[$hour] ?? 0) + 1;
            }
            arsort($hourCounts);
            $peakHour = key($hourCounts);
            $statsRows[] = [
                'Range' => $range,
                'Total Sales' => $totalSales,
                'Order Count' => $orderCount,
                'Top Product' => $topProduct,
                'Average Order Value' => $averageOrderValue,
                'Peak Hour' => $peakHour,
            ];
        }

        // --- Product Sales Matrix ---
        $periods = [
            'today' => ['label' => 'Last Day', 'days' => 1],
            '7days' => ['label' => 'Last 7 Days', 'days' => 7],
            '30days' => ['label' => 'Last 30 Days', 'days' => 30],
        ];
        $allProductNames = collect();
        $productSales = [];
        foreach ($periods as $key => $period) {
            [$pFrom, $pTo] = BusinessDay::rangeUtc(auth()->user(), $key);
            $orders = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $pFrom)
                ->where('created_at', '<', $pTo)
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
        $allProductNames = $allProductNames->unique()->sort()->values();
        $matrixRows = [];
        foreach ($allProductNames as $name) {
            $matrixRows[] = [
                'Product' => $name,
                'Last Day' => $productSales['today'][$name] ?? 0,
                'Last 7 Days' => $productSales['7days'][$name] ?? 0,
                'Last 30 Days' => $productSales['30days'][$name] ?? 0,
            ];
        }

        // --- Service & Operations Stats (month/today only for CSV) ---
        $serviceOpsStats = [
            'month' => $this->aggregateServiceOpsStats('month', $editorId),
            'today' => $this->aggregateServiceOpsStats('today', $editorId),
        ];

        // --- Product Category Stats (month only for CSV) ---
        $productCategoryStats = [
            'month' => $this->aggregateProductCategoryStats('month', $editorId),
        ];

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics-report.csv"',
        ];
        $callback = function() use ($months, $statsRows, $matrixRows, $serviceOpsStats, $productCategoryStats) {
            $file = fopen('php://output', 'w');
            // Neutralize CSV formula injection: any cell that a spreadsheet
            // could interpret as a formula is prefixed with a single quote.
            $sanitize = function ($value) {
                if (is_string($value) && $value !== ''
                    && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    return "'" . $value;
                }
                return $value;
            };
            $put = function ($handle, $row) use ($sanitize) {
                $row = is_array($row) ? $row : [$row];
                fputcsv($handle, array_map($sanitize, $row));
            };
            // A section with no rows must not crash the export (e.g. a
            // fresh venue with no sales in the window yet).
            $section = function ($title, $rows) use ($file, $put) {
                $put($file, [$title]);
                if (! empty($rows)) {
                    $put($file, array_keys(reset($rows)));
                    foreach ($rows as $row) {
                        $put($file, $row);
                    }
                }
                $put($file, []);
            };
            $section('Monthly Stats', $months);
            $section('Stats by Range', $statsRows);
            $section('Product Sales Matrix', $matrixRows);
            // Service & Operations (Month)
            $put($file, ['Service & Operations (Month)']);
            foreach ($serviceOpsStats['month'] as $key => $val) {
                if (is_array($val)) {
                    $put($file, [$key]);
                    foreach ($val as $item) {
                        $put($file, $item);
                    }
                } else {
                    $put($file, [$key, $val]);
                }
            }
            $put($file, []);
            // Product Category Stats (Month)
            $put($file, ['Product Category Stats (Month)']);
            foreach ($productCategoryStats['month'] as $key => $val) {
                if (is_array($val)) {
                    $put($file, [$key]);
                    foreach ($val as $item) {
                        $put($file, $item);
                    }
                } else {
                    $put($file, [$key, $val]);
                }
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    // Helper methods copied from Livewire component for PDF export
    private function aggregateServiceOpsStats($range, $editorId)
    {
        [$from, $to] = BusinessDay::rangeUtc(auth()->user(), $range);
        $sessionQuery = \App\Models\TableSession::query()->where('editor_id', $editorId)
            ->where('opened_at', '>=', $from)->where('opened_at', '<', $to);
        $orderQuery = \App\Models\Order::query()->countable()->where('editor_id', $editorId)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $activityQuery = \App\Models\ActivityLog::query()->where('editor_id', $editorId)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $sessions = $sessionQuery->get();
        $orders = $orderQuery->with('table')->get();
        $activities = $activityQuery->get();
        $tableCounts = $orders->groupBy('table_id')->map->count();
        $mostUsedTableId = $tableCounts->sortDesc()->keys()->first();
        $mostUsedTable = $mostUsedTableId ? (\App\Models\Table::find($mostUsedTableId)->table_number ?? $mostUsedTableId) : null;
        $durations = $sessions->map(function($s) { if ($s->opened_at && $s->closed_at) { return $s->closed_at->diffInMinutes($s->opened_at); } return null; })->filter();
        $avgSessionDuration = $durations->count() ? round($durations->avg(), 1) : null;
        $sessionsToday = $sessions->count();
        $sessionReopenings = $sessions->where('status', 'reopened')->count();
        $tableTurnover = $sessionsToday && $sessions->count() ? round($sessionsToday / $sessions->groupBy('table_id')->count(), 2) : null;
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
        $qrScans = $activities->where('type', 'qr_scan')->count();
        $qrToOrder = $qrScans ? round($orders->count() / $qrScans * 100, 1) : null;
        $avgTimeQrToOrder = null;
        $qrTimes = [];
        foreach ($activities->where('type', 'qr_scan') as $scan) {
            $order = $orders->where('table_id', $scan->table_id)->where('created_at', '>=', $scan->created_at)->sortBy('created_at')->first();
            if ($order) {
                $qrTimes[] = $order->created_at->diffInSeconds($scan->created_at);
            }
        }
        if (count($qrTimes)) {
            $avgTimeQrToOrder = round(array_sum($qrTimes) / count($qrTimes) / 60, 2);
        }
        $staffOrderCounts = $orders->groupBy('created_by')->map->count();
        $staffOrderCountsArr = [];
        foreach ($staffOrderCounts as $uid => $count) {
            $name = $uid ? (\App\Models\User::find($uid)->name ?? ('User #'.$uid)) : 'Guests (QR)';
            $staffOrderCountsArr[] = ['name' => $name, 'orders' => $count];
        }
        $tableUsage = $orders->groupBy('table_id')->map->count();
        $tableUsageArr = [];
        foreach ($tableUsage as $tid => $count) {
            $table = $tid ? (\App\Models\Table::find($tid)->table_number ?? $tid) : 'Unknown';
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

    private function aggregateProductCategoryStats($range, $editorId)
    {
        [$from, $to] = BusinessDay::rangeUtc(auth()->user(), $range);
        $orderQuery = \App\Models\Order::query()->countable()->where('editor_id', $editorId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);
        $orders = $orderQuery->with(['items.product'])->get();
        $productStats = [];
        $categoryStats = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pid = $item->product_id;
                $cid = $item->product ? $item->product->category_id : null;
                $revenue = $item->quantity * $item->price;
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
        $topProducts = collect($productStats)->sortByDesc('quantity')->take(5)->values()->all();
        $leastProducts = collect($productStats)->sortBy('quantity')->take(5)->values()->all();
        $categorySales = collect($categoryStats)->sortByDesc('revenue')->values()->all();
        $categoryOrders = collect($categoryStats)->sortByDesc('quantity')->values()->all();
        return [
            'top_products' => $topProducts,
            'least_products' => $leastProducts,
            'category_sales' => $categorySales,
            'category_orders' => $categoryOrders,
        ];
    }
}
