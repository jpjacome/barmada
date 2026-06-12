<?php

namespace App\Livewire;

use App\Support\BusinessDay;
use App\Support\VenueAnalytics;
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

    public $currency = '$';

    public function mount()
    {
        $this->currency = auth()->user() ? auth()->user()->currencySymbol() : '$';
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
        // Business months in the venue's timezone + cutoff. [F-22]
        $this->monthlyStats = VenueAnalytics::monthly(auth()->user());
    }

    public function buildProductSalesMatrix()
    {
        $this->productSalesMatrix = VenueAnalytics::productMatrix(auth()->user());
    }

    private function aggregateSalesAndRevenueStats($range)
    {
        // Ranges follow the venue's business day (timezone + cutoff) [F-22];
        // computation shared with the API via VenueAnalytics.
        return VenueAnalytics::summary(auth()->user(), $range);
    }

    private function aggregateProductCategoryStats($range)
    {
        return VenueAnalytics::productAndCategoryStats(auth()->user(), $range);
    }

    private function aggregateServiceOpsStats($range)
    {
        return VenueAnalytics::serviceOps(auth()->user(), $range);
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
        // Sales per business day for the current venue-local week
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $localNow = now()->setTimezone($user->businessTimezone());
        $startOfWeek = $localNow->copy()->startOfWeek();
        $labels = [];
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $dayLocal = $startOfWeek->copy()->addDays($i)->addHours($user->dayCutoffHour());
            $labels[] = $dayLocal->format('D');
            $total = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $dayLocal->copy()->setTimezone('UTC'))
                ->where('created_at', '<', $dayLocal->copy()->addDay()->setTimezone('UTC'))
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales ('.$this->currency.')',
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
        // Sales per business day for the previous venue-local week
        $user = auth()->user();
        $editorId = $user ? $user->id : null;
        $localNow = now()->setTimezone($user->businessTimezone());
        $startOfLastWeek = $localNow->copy()->startOfWeek()->subWeek();
        $labels = [];
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $dayLocal = $startOfLastWeek->copy()->addDays($i)->addHours($user->dayCutoffHour());
            $labels[] = $dayLocal->format('D');
            $total = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $dayLocal->copy()->setTimezone('UTC'))
                ->where('created_at', '<', $dayLocal->copy()->addDay()->setTimezone('UTC'))
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales ('.$this->currency.')',
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
            [$from, $to, $dayLocal] = BusinessDay::dayRangeUtc($user, $i);
            $labels[] = $dayLocal->format('M j');
            $total = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->sum('total_amount');
            $data[] = (float) $total;
        }
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Sales ('.$this->currency.')',
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
        $rangeKeys = ['30' => '30days', '7' => '7days', '1' => 'today'];
        $result = [];
        foreach ($periods as $key => $period) {
            [$from, $to] = BusinessDay::rangeUtc($user, $rangeKeys[$key] ?? 'today');
            $orders = \App\Models\Order::countable()->where('editor_id', $editorId)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
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
                    'label' => 'Revenue ('.$this->currency.')',
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
