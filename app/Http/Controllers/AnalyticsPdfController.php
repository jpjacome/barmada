<?php

namespace App\Http\Controllers;

use App\Support\VenueAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The analytics exports. Every figure comes from VenueAnalytics — the
 * same read models the web dashboard and the API call — so a PDF, a CSV
 * and the screen a manager is looking at cannot disagree. This
 * controller used to reimplement the whole aggregation pipeline three
 * times over, which is precisely how they drifted.
 */
class AnalyticsPdfController extends Controller
{
    public function export(Request $request)
    {
        return Pdf::loadView('analytics-dashboard-pdf', $this->viewData())
            ->download('analytics-dashboard.pdf');
    }

    public function exportWithCharts(Request $request)
    {
        $data = $this->viewData();
        // Charts are rendered client-side and posted back as data URLs.
        $data['chartImages'] = $request->only([
            'sales_chart',
            'sales_last_week_chart',
            'sales_last_month_chart',
            'product_bar_chart',
            'category_doughnut_chart',
            'category_revenue_doughnut_chart',
            'table_pie_chart',
        ]) + array_fill_keys([
            'sales_chart',
            'sales_last_week_chart',
            'sales_last_month_chart',
            'product_bar_chart',
            'category_doughnut_chart',
            'category_revenue_doughnut_chart',
            'table_pie_chart',
        ], null);

        return Pdf::loadView('analytics-dashboard-pdf', $data)
            ->download('analytics-dashboard.pdf');
    }

    public function exportCsv(Request $request)
    {
        $venue = auth()->user();

        $monthRows = [];
        foreach (VenueAnalytics::monthly($venue) as $month) {
            $monthRows[] = [
                'Month' => $month['label'],
                'Total Sales' => $month['total_sales'],
                'Order Count' => $month['order_count'],
                'Top Product' => $month['top_product'],
                'Average Order Value' => $month['average_order_value'],
                'Peak Hour' => $month['peak_hour'],
            ];
        }

        $statsRows = [];
        foreach (['today', '7days', '30days', 'month'] as $range) {
            $summary = VenueAnalytics::summary($venue, $range);
            $statsRows[] = [
                'Range' => $range,
                'Total Sales' => $summary['total_sales'],
                'Order Count' => $summary['order_count'],
                'Top Product' => $summary['top_product'],
                'Average Order Value' => $summary['average_order_value'],
                'Peak Hour' => $summary['peak_hour'],
            ];
        }

        $matrixRows = [];
        foreach (VenueAnalytics::productMatrix($venue) as $name => $sales) {
            $matrixRows[] = [
                'Product' => $name,
                'Last Day' => $sales['today'],
                'Last 7 Days' => $sales['7days'],
                'Last 30 Days' => $sales['30days'],
            ];
        }

        $serviceOpsStats = ['month' => VenueAnalytics::serviceOps($venue, 'month')];
        $productCategoryStats = ['month' => VenueAnalytics::productAndCategoryStats($venue, 'month')];

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics-report.csv"',
        ];

        $callback = function () use ($monthRows, $statsRows, $matrixRows, $serviceOpsStats, $productCategoryStats) {
            $file = fopen('php://output', 'w');
            // Neutralize CSV formula injection: any cell that a spreadsheet
            // could interpret as a formula is prefixed with a single quote.
            $sanitize = function ($value) {
                if (is_string($value) && $value !== ''
                    && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    return "'".$value;
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
            // Mixed sections: scalar metrics as key/value rows, nested
            // lists (staff, tables, products) as their own blocks.
            $mixedSection = function ($title, $values) use ($file, $put) {
                $put($file, [$title]);
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        $put($file, [$key]);
                        foreach ($value as $item) {
                            $put($file, $item);
                        }
                    } else {
                        $put($file, [$key, $value]);
                    }
                }
            };

            $section('Monthly Stats', $monthRows);
            $section('Stats by Range', $statsRows);
            $section('Product Sales Matrix', $matrixRows);
            $mixedSection('Service & Operations (Month)', $serviceOpsStats['month']);
            $put($file, []);
            $mixedSection('Product Category Stats (Month)', $productCategoryStats['month']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Everything analytics-dashboard-pdf renders. The PDF shows the
     * current business month plus today's sessions, so service ops are
     * gathered for both ranges and category stats for the month.
     */
    private function viewData(): array
    {
        $venue = auth()->user();
        $now = now();

        return [
            'now' => $now,
            'currentMonthKey' => $now->format('Y-n'),
            'prevMonth' => $now->copy()->subMonth(),
            'prevMonthKey' => $now->copy()->subMonth()->format('Y-n'),
            'monthlyStats' => VenueAnalytics::monthly($venue),
            'productSalesMatrix' => VenueAnalytics::productMatrix($venue),
            'serviceOpsStats' => [
                'month' => VenueAnalytics::serviceOps($venue, 'month'),
                'today' => VenueAnalytics::serviceOps($venue, 'today'),
            ],
            'productCategoryStats' => [
                'month' => VenueAnalytics::productAndCategoryStats($venue, 'month'),
            ],
            'currency' => $venue->currencySymbol(),
        ];
    }
}
