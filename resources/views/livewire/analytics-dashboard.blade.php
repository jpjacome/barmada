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
                    $prevMonth = \Carbon\Carbon::now()->subMonth();
                    $prevMonthName = $prevMonth->format('F');
                    $prevMonthYear = $prevMonth->format('Y');
                @endphp
                <div class="analytics-card-group-month analytics-card-group-month-grid">
                    <div class="analytics-card-group-title month-title">{{ date('F') }}</div>
                    <div class="analytics-card month-stats-col">
                        <ul>
                            <li>Sales for {{ date('F') }}: <strong>€2,900.00</strong></li>
                            <li>Orders for {{ date('F') }}: <strong>110</strong></li>
                            <li>Top Product: <strong>Nachos</strong></li>
                            <li>Average Order Value: <strong>€26.10</strong></li>
                            <li>Peak Hour: <strong>20:00-21:00</strong></li>
                        </ul>
                    </div>
                    <div class="analytics-card month-stats-col">
                        <div class="analytics-card-group-title analytics-dropdown-trigger" id="prevMonthTitle">
                            <span id="prevMonthLabel">{{ $prevMonthName }} {{ $prevMonthYear }}</span>
                            <i class="bi bi-caret-down-fill analytics-dropdown-caret"></i>
                        </div>
                        <div id="prevMonthDropdown" class="analytics-dropdown-menu">
                            <!-- Example: last 12 months -->
                            @for ($i = 1; $i <= 12; $i++)
                                @php
                                    $monthObj = \Carbon\Carbon::now()->subMonths($i);
                                @endphp
                                <div class="prev-month-option analytics-dropdown-option" data-month="{{ $monthObj->format('m') }}" data-year="{{ $monthObj->format('Y') }}">
                                    {{ $monthObj->format('F Y') }}
                                </div>
                            @endfor
                        </div>
                        <ul id="prevMonthStats">
                            <li>Sales for {{ $prevMonthName }}: <strong>€2,500.00</strong></li>
                            <li>Orders for {{ $prevMonthName }}: <strong>98</strong></li>
                            <li>Top Product: <strong>Beer</strong></li>
                            <li>Average Order Value: <strong>€25.50</strong></li>
                            <li>Peak Hour: <strong>19:00-20:00</strong></li>
                        </ul>
                    </div>
                </div>
                <div class="analytics-cards analytics-flex-gap">
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title">Today</div>
                        <div class="analytics-card">
                            <ul>
                                <li>Total Sales: <strong>€1,234.56</strong> <span class="trend-up">↑ 5%</span></li>
                                <li>Orders: <strong>42</strong> <span class="trend-up">↑ 3%</span></li>
                                <li>Top Product: <strong>Beer</strong></li>
                                <li>Average Order Value: <strong>€29.40</strong></li>
                                <li>Peak Hour: <strong>20:00-21:00</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title analytics-dropdown-trigger" id="last7DaysTitle">
                            <span id="last7DaysLabel">Last 7 Days</span>
                            <i class="bi bi-caret-down-fill analytics-dropdown-caret"></i>
                            <div id="last7DaysDropdown" class="analytics-dropdown-menu analytics-dropdown-menu-days">
                                @for ($i = 2; $i <= 30; $i++)
                                    <div class="last-days-option analytics-dropdown-option" data-days="{{ $i }}">Last {{ $i }} Days</div>
                                @endfor
                            </div>
                        </div>
                        <div class="analytics-card">
                            <ul id="last7DaysStats">
                                <li>Total Sales: <strong>€7,890.00</strong></li>
                                <li>Orders: <strong>312</strong></li>
                                <li>Top Product: <strong>Beer</strong></li>
                                <li>Average Order Value: <strong>€25.30</strong></li>
                                <li>Peak Hour: <strong>21:00-22:00</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group analytics-flex-1">
                        <div class="analytics-card-group-title analytics-dropdown-trigger" id="last30DaysTitle">
                            <span id="last30DaysLabel">Last 30 Days</span>
                            <i class="bi bi-caret-down-fill analytics-dropdown-caret"></i>
                            <div id="last30DaysDropdown" class="analytics-dropdown-menu analytics-dropdown-menu-days">
                                @for ($i = 7; $i <= 90; $i+=1)
                                    <div class="last-days-option analytics-dropdown-option" data-days="{{ $i }}">Last {{ $i }} Days</div>
                                @endfor
                            </div>
                        </div>
                        <div class="analytics-card">
                            <ul id="last30DaysStats">
                                <li>Total Sales: <strong>€32,450.00</strong></li>
                                <li>Orders: <strong>1,245</strong></li>
                                <li>Top Product: <strong>Nachos</strong></li>
                                <li>Average Order Value: <strong>€26.10</strong></li>
                                <li>Peak Hour: <strong>20:00-21:00</strong></li>
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
                                <li><strong>Beer</strong> (€1,200.00)</li>
                                <li><strong>Nachos</strong> (€1,000.00)</li>
                                <li><strong>Coke</strong> (€800.00)</li>
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                <li><strong>Whiskey</strong> (€20.00)</li>
                                <li><strong>Brandy</strong> (€25.00)</li>
                                <li><strong>Tequila</strong> (€30.00)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="analytics-cards">
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Today</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                <li><strong>Beer</strong> (€80.00)</li>
                                <li><strong>Nachos</strong> (€60.00)</li>
                                <li><strong>Coke</strong> (€50.00)</li>
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                <li><strong>Whiskey</strong> (€0.00)</li>
                                <li><strong>Brandy</strong> (€0.00)</li>
                                <li><strong>Tequila</strong> (€0.00)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Last 7 Days</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                <li><strong>Beer</strong> (€560.00)</li>
                                <li><strong>Nachos</strong> (€400.00)</li>
                                <li><strong>Coke</strong> (€350.00)</li>
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                <li><strong>Whiskey</strong> (€10.00)</li>
                                <li><strong>Brandy</strong> (€12.00)</li>
                                <li><strong>Tequila</strong> (€15.00)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="analytics-card-group">
                        <div class="analytics-card-group-title">Last 30 Days</div>
                        <div class="analytics-card">Top Selling Products:
                            <ul>
                                <li><strong>Beer</strong> (€2,400.00)</li>
                                <li><strong>Nachos</strong> (€1,800.00)</li>
                                <li><strong>Coke</strong> (€1,500.00)</li>
                            </ul>
                        </div>
                        <div class="analytics-card">Least Selling Products:
                            <ul>
                                <li><strong>Whiskey</strong> (€40.00)</li>
                                <li><strong>Brandy</strong> (€50.00)</li>
                                <li><strong>Tequila</strong> (€60.00)</li>
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
                                <tr><td>Beer</td><td>20</td><td>120</td><td>480</td></tr>
                                <tr><td>Nachos</td><td>10</td><td>80</td><td>320</td></tr>
                                <tr><td>Coke</td><td>8</td><td>60</td><td>240</td></tr>
                                <tr><td>Whiskey</td><td>0</td><td>2</td><td>8</td></tr>
                                <tr><td>Gin</td><td>1</td><td>3</td><td>12</td></tr>
                                <tr><td>Rum</td><td>2</td><td>10</td><td>40</td></tr>
                                <tr><td>Vodka</td><td>3</td><td>12</td><td>50</td></tr>
                                <tr><td>Tequila</td><td>1</td><td>5</td><td>20</td></tr>
                                <tr><td>Brandy</td><td>0</td><td>2</td><td>8</td></tr>
                                <tr><td>Wine</td><td>4</td><td>15</td><td>60</td></tr>
                                <tr><td>Martini</td><td>2</td><td>8</td><td>32</td></tr>
                                <tr><td>Champagne</td><td>1</td><td>6</td><td>24</td></tr>
                                <tr><td>Absinthe</td><td>0</td><td>1</td><td>4</td></tr>
                                <tr><td>Mezcal</td><td>1</td><td>4</td><td>16</td></tr>
                                <tr><td>Vermouth</td><td>0</td><td>2</td><td>8</td></tr>
                                <tr><td>Port</td><td>1</td><td>3</td><td>12</td></tr>
                                <tr><td>Sherry</td><td>0</td><td>2</td><td>8</td></tr>
                                <tr><td>Grappa</td><td>1</td><td>3</td><td>12</td></tr>
                                <tr><td>Baileys</td><td>2</td><td>7</td><td>28</td></tr>
                                <tr><td>Campari</td><td>1</td><td>5</td><td>20</td></tr>
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
                    @php
                        // Example data, replace with backend/Livewire data as needed
                        $categories = [
                            ['name' => 'Drinks', 'today' => 18, 'week' => 120, 'month' => 520],
                            ['name' => 'Snacks', 'today' => 9, 'week' => 60, 'month' => 210],
                            ['name' => 'Food', 'today' => 6, 'week' => 40, 'month' => 180],
                            ['name' => 'Cocktails', 'today' => 4, 'week' => 25, 'month' => 100],
                            ['name' => 'Desserts', 'today' => 2, 'week' => 10, 'month' => 40],
                            ['name' => 'Coffee', 'today' => 7, 'week' => 35, 'month' => 150],
                            ['name' => 'Tea', 'today' => 5, 'week' => 28, 'month' => 110],
                            ['name' => 'Wine', 'today' => 3, 'week' => 18, 'month' => 75],
                            ['name' => 'Beer', 'today' => 8, 'week' => 50, 'month' => 200],
                            ['name' => 'Appetizers', 'today' => 4, 'week' => 22, 'month' => 90],
                        ];
                    @endphp
                    @foreach ([
                        ['label' => 'Today', 'key' => 'today'],
                        ['label' => 'Last 7 Days', 'key' => 'week'],
                        ['label' => 'Last 30 Days', 'key' => 'month'],
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
                                @foreach (collect($categories)->sortByDesc($col['key']) as $cat)
                                    <li class="category-orders-item">
                                        <span>{{ $cat['name'] }}</span>
                                        <span><strong>{{ $cat[$col['key']] }}</strong></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="analytics-cards">
                    <div class="analytics-card">Drinks: <strong>€800</strong></div>
                    <div class="analytics-card">Snacks: <strong>€300</strong></div>
                    <div class="analytics-card">Food: <strong>€134</strong></div>
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
                    <div><strong>Most Used Table:</strong> Table 5</div>
                    <div><strong>Average Session Duration:</strong> 1h 12m</div>
                    <div><strong>Sessions Today:</strong> 18</div>
                    <div><strong>Session Reopenings:</strong> 2</div>
                    <div><strong>Table Turnover Rate:</strong> 3.2/day</div>
                    <div><strong>Downtime per Table:</strong> 15m</div>
                </div>
                <div class="analytics-card analytics-service-ops-col">
                    <div><strong>QR Scans (Today):</strong> 30</div>
                    <div><strong>QR to Order Conversion:</strong> 80%</div>
                    <div><strong>Avg. Time QR to Order:</strong> 2m 30s</div>
                </div>
                <div class="analytics-card analytics-service-ops-col">
                    <div><strong>Ana:</strong> 15 Orders</div>
                    <div><strong>Juan:</strong> 12 Orders</div>
                    <div><strong>Maria:</strong> 9 Orders</div>
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
<script src="{{ asset('js/analytics-dashboard.js') }}"></script>
@endsection
