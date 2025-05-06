<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Http;

#[Layout('layouts.app')]
class AnalyticsDashboard extends Component
{
    public $stats = [
        'today' => [
            'total_sales' => 0,
            'order_count' => 0,
            'top_product' => null,
            'average_order_value' => 0,
            'peak_hour' => null,
        ],
        '7days' => [
            'total_sales' => 0,
            'order_count' => 0,
            'top_product' => null,
            'average_order_value' => 0,
            'peak_hour' => null,
        ],
        '30days' => [
            'total_sales' => 0,
            'order_count' => 0,
            'top_product' => null,
            'average_order_value' => 0,
            'peak_hour' => null,
        ],
        'month' => [
            'total_sales' => 0,
            'order_count' => 0,
            'top_product' => null,
            'average_order_value' => 0,
            'peak_hour' => null,
        ],
    ];

    public $productCategoryStats = [
        'today' => [
            'top_products' => [],
            'least_products' => [],
            'category_sales' => [],
            'category_orders' => [],
        ],
        '7days' => [
            'top_products' => [],
            'least_products' => [],
            'category_sales' => [],
            'category_orders' => [],
        ],
        '30days' => [
            'top_products' => [],
            'least_products' => [],
            'category_sales' => [],
            'category_orders' => [],
        ],
        'month' => [
            'top_products' => [],
            'least_products' => [],
            'category_sales' => [],
            'category_orders' => [],
        ],
    ];

    public $serviceOpsStats = [
        'today' => [],
        '7days' => [],
        '30days' => [],
        'month' => [],
    ];

    public $range = 'today';

    public $monthlyStats = [];

    public $productSalesMatrix = [];

    public function mount()
    {
        $this->fetchAllStats();
        $this->fetchAllProductCategoryStats();
        $this->fetchAllServiceOpsStats();
        $this->fetchMonthlyStats();
        $this->buildProductSalesMatrix();
    }

    public function updatedRange()
    {
        $this->fetchAllStats();
        $this->fetchAllProductCategoryStats();
        $this->fetchAllServiceOpsStats();
    }

    public function fetchAllStats()
    {
        foreach (['today', '7days', '30days', 'month'] as $range) {
            $this->stats[$range] = $this->aggregateSalesAndRevenueStats($range);
        }
    }

    public function fetchAllProductCategoryStats()
    {
        foreach (['today', '7days', '30days', 'month'] as $range) {
            $this->productCategoryStats[$range] = $this->aggregateProductCategoryStats($range);
        }
    }

    public function fetchAllServiceOpsStats()
    {
        foreach (['today', '7days', '30days', 'month'] as $range) {
            $this->serviceOpsStats[$range] = $this->aggregateServiceOpsStats($range);
        }
    }

    public function fetchMonthlyStats()
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->copy()->subMonths($i);
            $month = $date->month;
            $year = $date->year;
            $query = \App\Models\Order::query()
                ->where('editor_id', $editorId)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
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
                $hour = $order->created_at->format('H:00');
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
        $this->monthlyStats = $months;
    }

    public function buildProductSalesMatrix()
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $periods = [
            'today' => ['label' => 'Last Day', 'days' => 1],
            '7days' => ['label' => 'Last 7 Days', 'days' => 7],
            '30days' => ['label' => 'Last 30 Days', 'days' => 30],
        ];
        $allProductNames = collect();
        $productSales = [];
        foreach ($periods as $key => $period) {
            $from = now()->copy()->subDays($period['days'] - 1)->startOfDay();
            $orders = \App\Models\Order::where('editor_id', $editorId)
                ->where('created_at', '>=', $from)
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
        $this->productSalesMatrix = $matrix;
    }

    private function aggregateSalesAndRevenueStats($range)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $query = \App\Models\Order::query()->where('editor_id', $editorId);
        switch ($range) {
            case 'today': $query->whereDate('created_at', $now->toDateString()); break;
            case '7days': $query->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay()); break;
            case '30days': $query->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay()); break;
            case 'month': $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year); break;
            default: $query->whereDate('created_at', $now->toDateString());
        }
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
            $hour = $order->created_at->format('H:00');
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
        ];
    }

    private function aggregateProductCategoryStats($range)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $orderQuery = \App\Models\Order::query()->where('editor_id', $editorId);
        switch ($range) {
            case 'today': $orderQuery->whereDate('created_at', $now->toDateString()); break;
            case '7days': $orderQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay()); break;
            case '30days': $orderQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay()); break;
            case 'month': $orderQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year); break;
            default: $orderQuery->whereDate('created_at', $now->toDateString());
        }
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

    private function aggregateServiceOpsStats($range)
    {
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $sessionQuery = \App\Models\TableSession::query()->where('editor_id', $editorId);
        $orderQuery = \App\Models\Order::query()->where('editor_id', $editorId);
        $activityQuery = \App\Models\ActivityLog::query()->where('editor_id', $editorId);
        switch ($range) {
            case 'today': $sessionQuery->whereDate('opened_at', $now->toDateString()); $orderQuery->whereDate('created_at', $now->toDateString()); $activityQuery->whereDate('created_at', $now->toDateString()); break;
            case '7days': $sessionQuery->where('opened_at', '>=', $now->copy()->subDays(7)->startOfDay()); $orderQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay()); $activityQuery->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay()); break;
            case '30days': $sessionQuery->where('opened_at', '>=', $now->copy()->subDays(30)->startOfDay()); $orderQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay()); $activityQuery->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay()); break;
            case 'month': $sessionQuery->whereMonth('opened_at', $now->month)->whereYear('opened_at', $now->year); $orderQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year); $activityQuery->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year); break;
            default: $sessionQuery->whereDate('opened_at', $now->toDateString()); $orderQuery->whereDate('created_at', $now->toDateString()); $activityQuery->whereDate('created_at', $now->toDateString());
        }
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
        $staffOrderCounts = $orders->groupBy('user_id')->map->count();
        $staffOrderCountsArr = [];
        foreach ($staffOrderCounts as $uid => $count) {
            $user = $uid ? (\App\Models\User::find($uid)->name ?? $uid) : 'Unknown';
            $staffOrderCountsArr[] = ['name' => $user, 'orders' => $count];
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

    public function getCategoryChartData($range = 'month')
    {
        $orders = $this->productCategoryStats[$range]['category_orders'] ?? [];
        $sales = $this->productCategoryStats[$range]['category_sales'] ?? [];
        return [
            'orders' => [
                'labels' => collect($orders)->pluck('name')->toArray(),
                'data' => collect($orders)->pluck('quantity')->toArray(),
            ],
            'revenue' => [
                'labels' => collect($sales)->pluck('name')->toArray(),
                'data' => collect($sales)->pluck('revenue')->toArray(),
            ],
        ];
    }

    // --- Chart.js Data Providers ---
    public function getSalesChartData()
    {
        // Sales per day for the current week (Sunday to Saturday)
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek();
        $labels = [];
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $labels[] = $date->format('D');
            $total = \App\Models\Order::where('editor_id', $editorId)
                ->whereDate('created_at', $date->toDateString())
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales (€)',
                    'data' => $data,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.4,
                    'fill' => true
                ]]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true],
                    'title' => ['display' => true, 'text' => 'Sales This Week']
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true]
                ]
            ]
        ];
    }

    public function getSalesLastWeekChartData()
    {
        // Sales per day for the previous week (Sunday to Saturday)
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $startOfLastWeek = $now->copy()->startOfWeek()->subWeek();
        $labels = [];
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfLastWeek->copy()->addDays($i);
            $labels[] = $date->format('D');
            $total = \App\Models\Order::where('editor_id', $editorId)
                ->whereDate('created_at', $date->toDateString())
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales (€)',
                    'data' => $data,
                    'borderColor' => 'rgba(255, 159, 64, 1)',
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'tension' => 0.4,
                    'fill' => true
                ]]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true],
                    'title' => ['display' => true, 'text' => 'Sales Last Week']
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true]
                ]
            ]
        ];
    }

    public function getSalesLastMonthChartData()
    {
        // Real per-day sales for last 30 days
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $now = now();
        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $labels[] = $date->format('M j');
            $total = \App\Models\Order::where('editor_id', $editorId)
                ->whereDate('created_at', $date->toDateString())
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales (€)',
                    'data' => $data,
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'tension' => 0.4,
                    'fill' => true
                ]]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true],
                    'title' => ['display' => true, 'text' => 'Sales Last 30 Days']
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true]
                ]
            ]
        ];
    }

    public function getProductBarChartData()
    {
        // Aggregate all products for each period (30, 7, 1 days)
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $periods = [
            '30' => ['label' => 'Last 30 Days', 'days' => 30],
            '7' => ['label' => 'Last 7 Days', 'days' => 7],
            '1' => ['label' => 'Today', 'days' => 1],
        ];
        $colorPalette = [
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(255, 99, 132, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(199, 199, 199, 0.7)',
            'rgba(255, 99, 255, 0.7)',
            'rgba(99, 255, 132, 0.7)',
            'rgba(255, 140, 0, 0.7)'
        ];
        $borderPalette = [
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(199, 199, 199, 1)',
            'rgba(255, 99, 255, 1)',
            'rgba(99, 255, 132, 1)',
            'rgba(255, 140, 0, 1)'
        ];
        $result = [];
        foreach ($periods as $key => $period) {
            $from = now()->copy()->subDays($period['days'] - 1)->startOfDay();
            $orders = \App\Models\Order::where('editor_id', $editorId)
                ->where('created_at', '>=', $from)
                ->with('items.product')
                ->get();
            $productStats = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $pid = $item->product_id;
                    $name = $item->product ? $item->product->name : 'Unknown';
                    if (!isset($productStats[$pid])) {
                        $productStats[$pid] = [
                            'name' => $name,
                            'revenue' => 0,
                            'orders' => 0
                        ];
                    }
                    $productStats[$pid]['revenue'] += $item->quantity * $item->price;
                    $productStats[$pid]['orders'] += $item->quantity;
                }
            }
            $labels = array_values(array_map(fn($p) => $p['name'], $productStats));
            $data = array_values(array_map(fn($p) => $p['revenue'], $productStats));
            $ordersArr = array_values(array_map(fn($p) => $p['orders'], $productStats));
            $barColors = [];
            $borderColors = [];
            for ($i = 0; $i < count($labels); $i++) {
                $barColors[] = $colorPalette[$i % count($colorPalette)];
                $borderColors[] = $borderPalette[$i % count($borderPalette)];
            }
            $result[$key] = [
                'labels' => $labels,
                'data' => $data,
                'orders' => $ordersArr,
                'datasets' => [[
                    'label' => 'Revenue (€)',
                    'data' => $data,
                    'backgroundColor' => $barColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1
                ]]
            ];
        }
        // Return the ranges directly for JS compatibility
        return $result;
    }

    public function render()
    {
        return view('livewire.analytics-dashboard');
    }
}
