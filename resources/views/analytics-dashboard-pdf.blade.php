<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analytics Dashboard PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2 { color: #333; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 18px; margin-bottom: 10px; }
        .stats-table, .product-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .stats-table th, .stats-table td, .product-table th, .product-table td { border: 1px solid #ccc; padding: 6px; }
        .stats-table th, .product-table th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Analytics Dashboard</h1>

    <div class="section">
        <div class="section-title">Sales & Revenue ({{ $now->format('F Y') }})</div>
        <table class="stats-table">
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>Sales</td><td>€{{ number_format($monthlyStats[$currentMonthKey]['total_sales'] ?? 0, 2) }}</td></tr>
            <tr><td>Orders</td><td>{{ $monthlyStats[$currentMonthKey]['order_count'] ?? 0 }}</td></tr>
            <tr><td>Top Product</td><td>{{ $monthlyStats[$currentMonthKey]['top_product'] ?? '—' }}</td></tr>
            <tr><td>Average Order Value</td><td>€{{ number_format($monthlyStats[$currentMonthKey]['average_order_value'] ?? 0, 2) }}</td></tr>
            <tr><td>Peak Hour</td><td>{{ $monthlyStats[$currentMonthKey]['peak_hour'] ?? '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Product Sales (Last 30 Days)</div>
        <table class="product-table">
            <tr><th>Product</th><th>Last Day</th><th>Last 7 Days</th><th>Last 30 Days</th></tr>
            @foreach ($productSalesMatrix as $productName => $sales)
                <tr>
                    <td>{{ $productName }}</td>
                    <td>{{ $sales['today'] }}</td>
                    <td>{{ $sales['7days'] }}</td>
                    <td>{{ $sales['30days'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">Service & Operations ({{ $now->format('F Y') }})</div>
        <table class="stats-table">
            <tr><th>Most Used Table</th><td>{{ $serviceOpsStats['month']['most_used_table'] ?? '—' }}</td></tr>
            <tr><th>Average Session Duration</th><td>{{ $serviceOpsStats['month']['avg_session_duration'] ? $serviceOpsStats['month']['avg_session_duration'] . ' min' : '—' }}</td></tr>
            <tr><th>Sessions Today</th><td>{{ $serviceOpsStats['today']['sessions_today'] ?? '—' }}</td></tr>
            <tr><th>Session Reopenings</th><td>{{ $serviceOpsStats['month']['session_reopenings'] ?? '—' }}</td></tr>
            <tr><th>Table Turnover Rate</th><td>{{ $serviceOpsStats['month']['table_turnover'] ?? '—' }}</td></tr>
            <tr><th>Downtime per Table</th><td>{{ $serviceOpsStats['month']['downtime_per_table'] ? $serviceOpsStats['month']['downtime_per_table'] . ' min' : '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Top Selling Products (This Month)</div>
        <ul>
            @foreach ($productCategoryStats['month']['top_products'] as $prod)
                <li>{{ $prod['name'] }} (€{{ number_format($prod['revenue'], 2) }})</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <div class="section-title">Least Selling Products (This Month)</div>
        <ul>
            @foreach ($productCategoryStats['month']['least_products'] as $prod)
                <li>{{ $prod['name'] }} (€{{ number_format($prod['revenue'], 2) }})</li>
            @endforeach
        </ul>
    </div>

    {{-- Charts Section at the End --}}
    @if(isset($chartImages) && (
        !empty($chartImages['sales_chart']) ||
        !empty($chartImages['sales_last_week_chart']) ||
        !empty($chartImages['sales_last_month_chart']) ||
        !empty($chartImages['product_bar_chart']) ||
        !empty($chartImages['category_doughnut_chart']) ||
        !empty($chartImages['category_revenue_doughnut_chart']) ||
        !empty($chartImages['table_pie_chart'])
    ))
        <div class="section">
            <div class="section-title">Charts</div>
            @if(!empty($chartImages['sales_chart']))
                <h2>Sales Chart</h2>
                <img src="{{ $chartImages['sales_chart'] }}" style="width:60%;max-width:350px;">
            @endif
            @if(!empty($chartImages['sales_last_week_chart']))
                <h2>Sales Last Week Chart</h2>
                <img src="{{ $chartImages['sales_last_week_chart'] }}" style="width:60%;max-width:350px;">
            @endif
            @if(!empty($chartImages['sales_last_month_chart']))
                <h2>Sales Last Month Chart</h2>
                <img src="{{ $chartImages['sales_last_month_chart'] }}" style="width:60%;max-width:350px;">
            @endif
            @if(!empty($chartImages['product_bar_chart']))
                <h2>Product Bar Chart</h2>
                <img src="{{ $chartImages['product_bar_chart'] }}" style="width:100%;max-width:600px;">
            @endif
            @if(!empty($chartImages['category_doughnut_chart']))
                <h2>Category Doughnut Chart</h2>
                <img src="{{ $chartImages['category_doughnut_chart'] }}" style="width:100%;max-width:600px;">
            @endif
            @if(!empty($chartImages['category_revenue_doughnut_chart']))
                <h2>Category Revenue Doughnut Chart</h2>
                <img src="{{ $chartImages['category_revenue_doughnut_chart'] }}" style="width:100%;max-width:600px;">
            @endif
            @if(!empty($chartImages['table_pie_chart']))
                <h2>Table Pie Chart</h2>
                <img src="{{ $chartImages['table_pie_chart'] }}" style="width:100%;max-width:600px;">
            @endif
        </div>
    @endif
</body>
</html>
