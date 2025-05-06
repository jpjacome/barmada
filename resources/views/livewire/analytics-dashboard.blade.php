@extends('layouts.app')

@section('content')
<div class="analytics-dashboard-container">
    <link href="{{ asset('css/analytics.css') }}" rel="stylesheet">
    <h1 class="analytics-title"><i class="bi bi-bar-chart"></i> Analytics Dashboard</h1>
    <div class="analytics-sections">
        <!-- Sales & Revenue (with chart) -->
        <section class="analytics-section analytics-section-sales">
            <div class="analytics-section-header">
                <i class="bi bi-cash-coin"></i>
                <h2>Sales & Revenue</h2>
            </div>
            <div class="analytics-section-grid">
                @php
                    $now = \Carbon\Carbon::now();
                    $currentMonthKey = $now->format('Y-n');
                    $prevMonth = $now->copy()->subMonth();
                    $prevMonthKey = $prevMonth->format('Y-n');
                @endphp
                <div class="analytics-card-group-month analytics-card-group-month-grid">
                    <div class="analytics-card-group-title month-title">{{ $now->format('F') }}</div>
                    <div class="analytics-card month-stats-col">
                        <ul>
                            <li>Sales for {{ $now->format('F') }}: <strong>€{{ number_format($monthlyStats[$currentMonthKey]['total_sales'] ?? 0, 2) }}</strong></li>
                            <li>Orders for {{ $now->format('F') }}: <strong>{{ $monthlyStats[$currentMonthKey]['order_count'] ?? 0 }}</strong></li>
                            <li>Top Product: <strong>{{ $monthlyStats[$currentMonthKey]['top_product'] ?? '—' }}</strong></li>
                            <li>Average Order Value: <strong>€{{ number_format($monthlyStats[$currentMonthKey]['average_order_value'] ?? 0, 2) }}</strong></li>
                            <li>Peak Hour: <strong>{{ $monthlyStats[$currentMonthKey]['peak_hour'] ?? '—' }}</strong></li>
                        </ul>
                    </div>
                    <div class="analytics-card month-stats-col">
                        <div class="analytics-card-group-title analytics-dropdown-trigger" id="prevMonthTitle">
                            <span id="prevMonthLabel">{{ $prevMonth->format('F Y') }}</span>
                            <i class="bi bi-caret-down-fill analytics-dropdown-caret"></i>
                        </div>
                        <div id="prevMonthDropdown" class="analytics-dropdown-menu">
                            @for ($i = 1; $i <= 12; $i++)
                                @php
                                    $monthObj = $now->copy()->subMonths($i);
                                    $monthKey = $monthObj->format('Y-n');
                                @endphp
                                <div class="prev-month-option analytics-dropdown-option" data-month="{{ $monthObj->format('m') }}" data-year="{{ $monthObj->format('Y') }}" data-key="{{ $monthKey }}">
                                    {{ $monthObj->format('F Y') }}
                                </div>
                            @endfor
                        </div>
                        <ul id="prevMonthStats">
                            <li>Sales for {{ $prevMonth->format('F') }}: <strong>€{{ number_format($monthlyStats[$prevMonthKey]['total_sales'] ?? 0, 2) }}</strong></li>
                            <li>Orders for {{ $prevMonth->format('F') }}: <strong>{{ $monthlyStats[$prevMonthKey]['order_count'] ?? 0 }}</strong></li>
                            <li>Top Product: <strong>{{ $monthlyStats[$prevMonthKey]['top_product'] ?? '—' }}</strong></li>
                            <li>Average Order Value: <strong>€{{ number_format($monthlyStats[$prevMonthKey]['average_order_value'] ?? 0, 2) }}</strong></li>
                            <li>Peak Hour: <strong>{{ $monthlyStats[$prevMonthKey]['peak_hour'] ?? '—' }}</strong></li>
                        </ul>
                    </div>
                </div>
                <div class="analytics-cards analytics-flex-gap">
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title">
                            <select wire:model="range" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                                <option value="today">Today</option>
                                <option value="7days">Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                        <div class="analytics-card">
                            <ul>
                                <li>Total Sales: <strong>€{{ number_format($stats[$range]['total_sales'], 2) }}</strong></li>
                                <li>Orders: <strong>{{ $stats[$range]['order_count'] }}</strong></li>
                                <li>Top Product: <strong>{{ $stats[$range]['top_product'] ?? '—' }}</strong></li>
                                <li>Average Order Value: <strong>€{{ number_format($stats[$range]['average_order_value'], 2) }}</strong></li>
                                <li>Peak Hour: <strong>{{ $stats[$range]['peak_hour'] ?? '—' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title">Today</div>
                        <div class="analytics-card">
                            <ul>
                                <li>Total Sales: <strong>€{{ number_format($stats['today']['total_sales'], 2) }}</strong></li>
                                <li>Orders: <strong>{{ $stats['today']['order_count'] }}</strong></li>
                                <li>Top Product: <strong>{{ $stats['today']['top_product'] ?? '—' }}</strong></li>
                                <li>Average Order Value: <strong>€{{ number_format($stats['today']['average_order_value'], 2) }}</strong></li>
                                <li>Peak Hour: <strong>{{ $stats['today']['peak_hour'] ?? '—' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title">Last 7 Days</div>
                        <div class="analytics-card">
                            <ul>
                                <li>Total Sales: <strong>€{{ number_format($stats['7days']['total_sales'], 2) }}</strong></li>
                                <li>Orders: <strong>{{ $stats['7days']['order_count'] }}</strong></li>
                                <li>Top Product: <strong>{{ $stats['7days']['top_product'] ?? '—' }}</strong></li>
                                <li>Average Order Value: <strong>€{{ number_format($stats['7days']['average_order_value'], 2) }}</strong></li>
                                <li>Peak Hour: <strong>{{ $stats['7days']['peak_hour'] ?? '—' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title">Last 30 Days</div>
                        <div class="analytics-card">
                            <ul>
                                <li>Total Sales: <strong>€{{ number_format($stats['30days']['total_sales'], 2) }}</strong></li>
                                <li>Orders: <strong>{{ $stats['30days']['order_count'] }}</strong></li>
                                <li>Top Product: <strong>{{ $stats['30days']['top_product'] ?? '—' }}</strong></li>
                                <li>Average Order Value: <strong>€{{ number_format($stats['30days']['average_order_value'], 2) }}</strong></li>
                                <li>Peak Hour: <strong>{{ $stats['30days']['peak_hour'] ?? '—' }}</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="analytics-charts-row">
                    <div class="analytics-chart-container chart-bg">
                        <canvas id="salesChart"></canvas>
                    </div>
                    <div class="analytics-chart-container chart-bg">
                        <canvas id="salesLastWeekChart"></canvas>
                    </div>
                    <div class="analytics-chart-container chart-bg">
                        <canvas id="salesLastMonthChart"></canvas>
                    </div>
                </div>
            </div>
        </section>
        <!-- Chart Modal -->
        <div id="chartModal" class="chart-modal-overlay">
            <div class="chart-modal-content">
                <button class="chart-modal-close" onclick="closeChartModal()">&times;</button>
                <canvas id="modalChartCanvas" width="900" height="500"></canvas>
            </div>
        </div>
        <!-- Product & Category Analytics (merged) -->
        <section class="analytics-section analytics-section-products">
            <div class="analytics-section-header">
                <i class="bi bi-cup-straw"></i>
                <h2>Product & Category Analytics</h2>
            </div>
            <div class="analytics-section-grid">
                <div class="analytics-cards">
                    <div class="analytics-card-group analytics-card-group-month">
                        <div class="analytics-card-group-title">{{ date('F') }}</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['month']['top_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['month']['least_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="analytics-cards">
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Today</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['today']['top_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['today']['least_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Last 7 Days</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['7days']['top_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['7days']['least_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Last 30 Days</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['30days']['top_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                @foreach ($productCategoryStats['30days']['least_products'] as $prod)
                                    <li><strong>{{ $prod['name'] }}</strong> (€{{ number_format($prod['revenue'], 2) }})</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="analytics-table-row analytics-table-row-fullwidth">
                    <div class="product-sales-table-wrapper">
                        <table class="product-sales-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Last Day</th>
                                    <th>Last 7 Days</th>
                                    <th>Last 30 Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($productSalesMatrix as $productName => $sales)
                                    <tr>
                                        <td>{{ $productName }}</td>
                                        <td>{{ $sales['today'] }}</td>
                                        <td>{{ $sales['7days'] }}</td>
                                        <td>{{ $sales['30days'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4">No product sales data available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="product-sales-table-resize-handle" id="productSalesResizeHandle"></div>
                    </div>
                </div>
                <div class="analytics-chart-container chart-bg analytics-product-sales-chart-fullwidth">
                    <div class="analytics-chart-controls">
                        <select id="productBarChartRange">
                            <option value="30">Last 30 Days</option>
                            <option value="7">Last 7 Days</option>
                            <option value="1">Today</option>
                        </select>
                    </div>    
                    <canvas id="productBarChart"></canvas>
                </div>
                <div class="category-orders-cards-scroll">
                    @foreach ([
                        ['label' => 'Today', 'key' => 'today'],
                        ['label' => 'Last 7 Days', 'key' => '7days'],
                        ['label' => 'Last 30 Days', 'key' => '30days'],
                        ['label' => 'This Month', 'key' => 'month'],
                    ] as $col)
                    <div class="category-orders-card">
                        <div class="category-orders-title">
                            <span>{{ $col['label'] }}</span>
                            <div class="container">
                                <button class="toggle-metric-btn" data-period="{{ $col['key'] }}">
                                    <i class="bi bi-hash"></i>
                                    <i class="bi bi-currency-dollar" style="display:none;"></i>
                                </button>
                                <button class="sort-btn" data-period="{{ $col['key'] }}">
                                    <i class="bi bi-sort-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="category-orders-list" id="category-orders-list-{{ $col['key'] }}">
                            <ul>
                                @foreach ($productCategoryStats[$col['key']]['category_orders'] as $cat)
                                    <li class="category-orders-item">
                                        <span>{{ $cat['name'] }}</span>
                                        <span><strong>{{ $cat['quantity'] }}</strong></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="analytics-cards analytics-category-charts-row">
                    <div class="analytics-card analytics-chart-container chart-bg">
                        <canvas id="categoryDoughnutChart"></canvas>
                    </div>
                    <div class="analytics-card analytics-chart-container chart-bg">
                        <canvas id="categoryRevenueDoughnutChart"></canvas>
                    </div>
                </div>
            </div>
        </section>
        <!-- Service & Operations Analytics -->
        <section class="analytics-section analytics-section-service-operations">
            <div class="analytics-section-header">
                <i class="bi bi-gear"></i>
                <h2>Service & Operations Analytics</h2>
            </div>
            <div class="analytics-service-ops-columns">
                <div class="analytics-card analytics-service-ops-col">
                    <div><strong>Most Used Table:</strong> {{ $serviceOpsStats['month']['most_used_table'] ?? '—' }}</div>
                    <div><strong>Average Session Duration:</strong> {{ $serviceOpsStats['month']['avg_session_duration'] ? $serviceOpsStats['month']['avg_session_duration'] . ' min' : '—' }}</div>
                    <div><strong>Sessions Today:</strong> {{ $serviceOpsStats['today']['sessions_today'] ?? '—' }}</div>
                    <div><strong>Session Reopenings:</strong> {{ $serviceOpsStats['month']['session_reopenings'] ?? '—' }}</div>
                    <div><strong>Table Turnover Rate:</strong> {{ $serviceOpsStats['month']['table_turnover'] ?? '—' }}</div>
                    <div><strong>Downtime per Table:</strong> {{ $serviceOpsStats['month']['downtime_per_table'] ? $serviceOpsStats['month']['downtime_per_table'] . ' min' : '—' }}</div>
                </div>
                <div class="analytics-card analytics-service-ops-col">
                    <div><strong>QR Scans (Today):</strong> {{ $serviceOpsStats['today']['qr_scans'] ?? '—' }}</div>
                    <div><strong>QR to Order Conversion:</strong> {{ $serviceOpsStats['month']['qr_to_order_conversion'] ? $serviceOpsStats['month']['qr_to_order_conversion'] . '%' : '—' }}</div>
                    <div><strong>Avg. Time QR to Order:</strong> {{ $serviceOpsStats['month']['avg_time_qr_to_order'] ? $serviceOpsStats['month']['avg_time_qr_to_order'] . ' min' : '—' }}</div>
                </div>
                <div class="analytics-card analytics-service-ops-col">
                    @foreach (($serviceOpsStats['month']['staff_order_counts'] ?? []) as $staff)
                        <div><strong>{{ $staff['name'] }}:</strong> {{ $staff['orders'] }} Orders</div>
                    @endforeach
                </div>
            </div>
            <div class="analytics-chart-container chart-bg">
                <div class="analytics-chart-controls">
                    <select id="tablePieChartRange">
                        <option value="all">All Time</option>
                        <option value="month">Last 30 Days</option>
                        <option value="week">Last 7 Days</option>
                        <option value="day">Today</option>
                    </select>
                </div>
                <canvas id="tablePieChart"></canvas>
            </div>
        </section>
        <!-- Advanced/Custom Analytics -->
        <section class="analytics-section analytics-section-advanced">
            <div class="analytics-cards">
                <div class="analytics-card">Export: <button>CSV</button> <button>PDF</button></div>
            </div>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@php
    $tablePieData = [
        'all' => [
            'labels' => collect($serviceOpsStats['month']['table_usage_distribution'] ?? [])->pluck('table')->filter()->values()->toArray(),
            'data' => collect($serviceOpsStats['month']['table_usage_distribution'] ?? [])->pluck('orders')->filter()->values()->toArray(),
        ],
        'month' => [
            'labels' => collect($serviceOpsStats['month']['table_usage_distribution'] ?? [])->pluck('table')->filter()->values()->toArray(),
            'data' => collect($serviceOpsStats['month']['table_usage_distribution'] ?? [])->pluck('orders')->filter()->values()->toArray(),
        ],
        'week' => [
            'labels' => collect($serviceOpsStats['7days']['table_usage_distribution'] ?? [])->pluck('table')->filter()->values()->toArray(),
            'data' => collect($serviceOpsStats['7days']['table_usage_distribution'] ?? [])->pluck('orders')->filter()->values()->toArray(),
        ],
        'day' => [
            'labels' => collect($serviceOpsStats['today']['table_usage_distribution'] ?? [])->pluck('table')->filter()->values()->toArray(),
            'data' => collect($serviceOpsStats['today']['table_usage_distribution'] ?? [])->pluck('orders')->filter()->values()->toArray(),
        ],
    ];
@endphp
<script>
    window.salesChartData = @json($this->getSalesChartData());
    window.salesLastWeekChartData = @json($this->getSalesLastWeekChartData());
    window.salesLastMonthChartData = @json($this->getSalesLastMonthChartData());
    window.productBarChartData = @json($this->getProductBarChartData());
    window.categoryChartData = @json($this->getCategoryChartData('month'));
    window.tablePieChartData = @json($tablePieData);
    window.monthlyStatsData = @json($monthlyStats);
</script>
<script src="{{ asset('js/analytics-dashboard.js') }}"></script>
@endsection
