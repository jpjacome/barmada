// All analytics dashboard JS moved from Blade file

// Chart.js is loaded via CDN in the Blade file
window.analyticsCharts = {};

document.addEventListener('DOMContentLoaded', function() {
    // --- Sales & Revenue Dropdowns ---
    const prevMonthTitle = document.getElementById('prevMonthTitle');
    const prevMonthDropdown = document.getElementById('prevMonthDropdown');
    const prevMonthLabel = document.getElementById('prevMonthLabel');
    const prevMonthStats = document.getElementById('prevMonthStats');
    if (prevMonthTitle && prevMonthDropdown) {
        prevMonthTitle.addEventListener('click', function(e) {
            prevMonthDropdown.style.display = prevMonthDropdown.style.display === 'block' ? 'none' : 'block';
            e.stopPropagation();
        });
        document.addEventListener('click', function() {
            prevMonthDropdown.style.display = 'none';
        });
        prevMonthDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            if (e.target.classList.contains('prev-month-option')) {
                const key = e.target.getAttribute('data-key');
                const label = e.target.textContent;
                prevMonthLabel.textContent = label;
                let stats = window.monthlyStatsData || {};
                let s = stats[key] || {total_sales:'N/A',order_count:'N/A',top_product:'-',average_order_value:'-',peak_hour:'-'};
                prevMonthStats.innerHTML = `
                    <li>Sales for ${label}: <strong>€${s.total_sales !== undefined ? Number(s.total_sales).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : 'N/A'}</strong></li>
                    <li>Orders for ${label}: <strong>${s.order_count ?? 'N/A'}</strong></li>
                    <li>Top Product: <strong>${s.top_product ?? '-'}</strong></li>
                    <li>Average Order Value: <strong>€${s.average_order_value !== undefined ? Number(s.average_order_value).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '-'}</strong></li>
                    <li>Peak Hour: <strong>${s.peak_hour ?? '-'}</strong></li>
                `;
                prevMonthDropdown.style.display = 'none';
            }
        });
    }

    // --- Last 7/30 Days Dropdowns ---
    // Use dynamic data injected from Blade/Livewire
    const last7Title = document.getElementById('last7DaysTitle');
    const last7Dropdown = document.getElementById('last7DaysDropdown');
    const last7Label = document.getElementById('last7DaysLabel');
    const last7Stats = document.getElementById('last7DaysStats');
    if (last7Title && last7Dropdown) {
        last7Title.addEventListener('click', function(e) {
            last7Dropdown.style.display = last7Dropdown.style.display === 'block' ? 'none' : 'block';
            e.stopPropagation();
        });
        document.addEventListener('click', function() {
            last7Dropdown.style.display = 'none';
        });
        last7Dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            if (e.target.classList.contains('last-days-option')) {
                const days = e.target.getAttribute('data-days');
                last7Label.textContent = `Last ${days} Days`;
                let stats = window.last7DaysStatsData || {};
                let s = stats[days] || {sales:'N/A',orders:'N/A',top:'-',avg:'-',peak:'-'};
                last7Stats.innerHTML = `
                    <li>Total Sales: <strong>${s.sales}</strong></li>
                    <li>Orders: <strong>${s.orders}</strong></li>
                    <li>Top Product: <strong>${s.top}</strong></li>
                    <li>Average Order Value: <strong>${s.avg}</strong></li>
                    <li>Peak Hour: <strong>${s.peak}</strong></li>
                `;
                last7Dropdown.style.display = 'none';
            }
        });
    }
    const last30Title = document.getElementById('last30DaysTitle');
    const last30Dropdown = document.getElementById('last30DaysDropdown');
    const last30Label = document.getElementById('last30DaysLabel');
    const last30Stats = document.getElementById('last30DaysStats');
    if (last30Title && last30Dropdown) {
        last30Title.addEventListener('click', function(e) {
            last30Dropdown.style.display = last30Dropdown.style.display === 'block' ? 'none' : 'block';
            e.stopPropagation();
        });
        document.addEventListener('click', function() {
            last30Dropdown.style.display = 'none';
        });
        last30Dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            if (e.target.classList.contains('last-days-option')) {
                const days = e.target.getAttribute('data-days');
                last30Label.textContent = `Last ${days} Days`;
                let stats = window.last30DaysStatsData || {};
                let s = stats[days] || {sales:'N/A',orders:'N/A',top:'-',avg:'-',peak:'-'};
                last30Stats.innerHTML = `
                    <li>Total Sales: <strong>${s.sales}</strong></li>
                    <li>Orders: <strong>${s.orders}</strong></li>
                    <li>Top Product: <strong>${s.top}</strong></li>
                    <li>Average Order Value: <strong>${s.avg}</strong></li>
                    <li>Peak Hour: <strong>${s.peak}</strong></li>
                `;
                last30Dropdown.style.display = 'none';
            }
        });
    }

    // --- Sales & Category Charts ---
    if (window.Chart) {
        // Sales This Week (dynamic)
        var ctx = document.getElementById('salesChart').getContext('2d');
        window.analyticsCharts['salesChart'] = new Chart(ctx, window.salesChartData);
        // Sales Last Week (dynamic)
        var ctxLastWeek = document.getElementById('salesLastWeekChart').getContext('2d');
        window.analyticsCharts['salesLastWeekChart'] = new Chart(ctxLastWeek, window.salesLastWeekChartData);
        // Sales Last Month (dynamic)
        var ctxLastMonth = document.getElementById('salesLastMonthChart').getContext('2d');
        window.analyticsCharts['salesLastMonthChart'] = new Chart(ctxLastMonth, window.salesLastMonthChartData);

        // Fix chart container height for preview/scroll issues
        [ctx.canvas, ctxLastWeek.canvas, ctxLastMonth.canvas].forEach(function(canvas) {
            canvas.style.height = '220px';
            canvas.style.maxHeight = '220px';
            canvas.parentElement.style.height = '240px';
            canvas.parentElement.style.maxHeight = '240px';
        });
        // Force Chart.js to re-render axes
        [window.analyticsCharts['salesChart'], window.analyticsCharts['salesLastWeekChart'], window.analyticsCharts['salesLastMonthChart']].forEach(function(chart) {
            if (chart) {
                chart.options.maintainAspectRatio = false;
                chart.options.responsive = false;
                chart.resize();
                chart.update('none');
            }
        });

        // Product Bar Chart (dynamic, with dropdown)
        var ctx1 = document.getElementById('productBarChart').getContext('2d');
        var defaultRange = '30';
        var productBarChart = new Chart(ctx1, {
            type: 'bar',
            data: window.productBarChartData[defaultRange],
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'All Products Revenue (Last 30 Days)' }
                },
                scales: { y: { beginAtZero: true } }
            }
        });
        window.analyticsCharts['productBarChart'] = productBarChart;
        var productBarChartRange = document.getElementById('productBarChartRange');
        if (productBarChartRange && productBarChart) {
            productBarChartRange.addEventListener('change', function() {
                var val = productBarChartRange.value;
                var d = window.productBarChartData[val];
                productBarChart.data.labels = d.labels;
                productBarChart.data.datasets = d.datasets;
                // Update chart title
                var titleMap = {
                    '30': 'All Products Revenue (Last 30 Days)',
                    '7': 'All Products Revenue (Last 7 Days)',
                    '1': 'All Products Revenue (Today)'
                };
                productBarChart.options.plugins.title.text = titleMap[val];
                productBarChart.update();
            });
        }

        // --- Dynamic Category Doughnut Chart Data Integration ---
        // Expect window.categoryChartData to be injected as JSON from Blade/Livewire
        if (window.Chart && window.categoryChartData) {
            // Category Orders Doughnut Chart
            var ctx2 = document.getElementById('categoryDoughnutChart').getContext('2d');
            window.analyticsCharts['categoryDoughnutChart'] = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: window.categoryChartData.orders.labels,
                    datasets: [{
                        data: window.categoryChartData.orders.data,
                        backgroundColor: [
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
                        ],
                        borderColor: [
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
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        title: { display: true, text: 'Sales by Category (Number of Orders)' },
                        legend: { display: true, position: 'bottom' }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            // Category Revenue Doughnut Chart
            var ctx3 = document.getElementById('categoryRevenueDoughnutChart').getContext('2d');
            window.analyticsCharts['categoryRevenueDoughnutChart'] = new Chart(ctx3, {
                type: 'doughnut',
                data: {
                    labels: window.categoryChartData.revenue.labels,
                    datasets: [{
                        data: window.categoryChartData.revenue.data,
                        backgroundColor: [
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
                        ],
                        borderColor: [
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
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        title: { display: true, text: 'Sales by Category (Revenue)' },
                        legend: { display: true, position: 'bottom' }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    // --- Table, QR, Staff Charts ---
    if (window.Chart) {
        // Table Pie Chart
        var tablePieChartElem = document.getElementById('tablePieChart');
        if (tablePieChartElem && window.tablePieChartData) {
            var defaultRange = 'all';
            window.analyticsCharts['tablePieChart'] = new Chart(tablePieChartElem.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: window.tablePieChartData[defaultRange].labels,
                    datasets: [{
                        label: 'Table Usage',
                        data: window.tablePieChartData[defaultRange].data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                            'rgba(255, 159, 64, 0.5)',
                            'rgba(199, 199, 199, 0.5)',
                            'rgba(83, 102, 255, 0.5)',
                            'rgba(255, 99, 255, 0.5)',
                            'rgba(99, 255, 132, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(199, 199, 199, 1)',
                            'rgba(83, 102, 255, 1)',
                            'rgba(255, 99, 255, 1)',
                            'rgba(99, 255, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: {
                        title: { display: true, text: 'Table Usage Distribution' },
                        legend: { display: true, position: 'bottom' }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // --- Table Pie Chart Dropdown Logic ---
        var tablePieChartRange = document.getElementById('tablePieChartRange');
        var tablePieChart = window.analyticsCharts['tablePieChart'];
        if (tablePieChartRange && tablePieChart && window.tablePieChartData) {
            tablePieChartRange.addEventListener('change', function() {
                var val = tablePieChartRange.value;
                var d = window.tablePieChartData[val];
                tablePieChart.data.labels = d.labels;
                tablePieChart.data.datasets[0].data = d.data;
                // Update chart title
                var titleMap = {
                    'all': 'Table Usage Distribution (All Time)',
                    'month': 'Table Usage Distribution (Last 30 Days)',
                    'week': 'Table Usage Distribution (Last 7 Days)',
                    'day': 'Table Usage Distribution (Today)'
                };
                tablePieChart.options.plugins.title.text = titleMap[val];
                tablePieChart.update();
            });
        }
    }

    // --- Product Sales Table Resize ---
    const wrapper = document.querySelector('.product-sales-table-wrapper');
    const handle = document.getElementById('productSalesResizeHandle');
    let isResizing = false;
    let startY = 0;
    let startHeight = 0;
    if (wrapper && handle) {
        handle.addEventListener('mousedown', function(e) {
            isResizing = true;
            startY = e.clientY;
            startHeight = wrapper.offsetHeight;
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('mousemove', function(e) {
            if (!isResizing) return;
            const newHeight = Math.max(120, startHeight + (e.clientY - startY));
            wrapper.style.maxHeight = newHeight + 'px';
        });
        document.addEventListener('mouseup', function() {
            isResizing = false;
            document.body.style.userSelect = '';
        });
    }

    // --- Category Orders Card Sorting ---
    const sortStates = { today: 'asc', week: 'asc', month: 'asc' };
    document.querySelectorAll('.sort-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const period = btn.getAttribute('data-period');
            const listContainer = document.getElementById('category-orders-list-' + period);
            if (!listContainer) return;
            const ul = listContainer.querySelector('ul');
            if (!ul) return;
            const items = Array.from(ul.querySelectorAll('li'));
            if (sortStates[period] === 'desc') {
                items.sort(function(a, b) {
                    const aVal = parseInt(a.querySelector('strong').textContent.replace(/[^\d]/g, ''));
                    const bVal = parseInt(b.querySelector('strong').textContent.replace(/[^\d]/g, ''));
                    return bVal - aVal;
                });
            } else {
                items.sort(function(a, b) {
                    const aVal = parseInt(a.querySelector('strong').textContent.replace(/[^\d]/g, ''));
                    const bVal = parseInt(b.querySelector('strong').textContent.replace(/[^\d]/g, ''));
                    return aVal - bVal;
                });
            }
            items.forEach(function(li) { ul.appendChild(li); });
            sortStates[period] = sortStates[period] === 'desc' ? 'asc' : 'desc';
            const icon = btn.querySelector('i');
            if (icon) {
                if (sortStates[period] === 'desc') {
                    icon.classList.remove('bi-sort-up');
                    icon.classList.add('bi-sort-down');
                } else {
                    icon.classList.remove('bi-sort-down');
                    icon.classList.add('bi-sort-up');
                }
            }
        });
    });

    // --- Chart Modal Logic ---
    let modalChartInstance = null;
    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }
    window.openChartModal = function(chartId) {
        const modal = document.getElementById('chartModal');
        const modalCanvas = document.getElementById('modalChartCanvas');
        modal.style.display = 'flex';
        if (modalChartInstance) {
            modalChartInstance.destroy();
        }
        const chartInstance = window.analyticsCharts[chartId];
        if (chartInstance) {
            modalChartInstance = new Chart(modalCanvas.getContext('2d'), {
                type: chartInstance.config.type,
                data: deepClone(chartInstance.data),
                options: Object.assign(deepClone(chartInstance.options), {responsive: false, maintainAspectRatio: false})
            });
        }
    };
    window.closeChartModal = function() {
        document.getElementById('chartModal').style.display = 'none';
        if (modalChartInstance) {
            modalChartInstance.destroy();
            modalChartInstance = null;
        }
    };
    [
        'salesChart',
        'salesLastWeekChart',
        'salesLastMonthChart',
        'productBarChart',
        'categoryDoughnutChart',
        'categoryRevenueDoughnutChart',
        'tablePieChart'
    ].forEach(function(id) {
        const chartCanvas = document.getElementById(id);
        if (chartCanvas) {
            chartCanvas.style.cursor = 'pointer';
            chartCanvas.addEventListener('click', function() {
                window.openChartModal(id);
            });
        }
    });
});
