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
                const month = e.target.getAttribute('data-month');
                const year = e.target.getAttribute('data-year');
                const label = e.target.textContent;
                prevMonthLabel.textContent = label;
                // Dummy data for demonstration
                let stats = {
                    '04-2025': {
                        sales: '€2,500.00', orders: '98', top: 'Beer', avg: '€25.50', peak: '19:00-20:00'
                    },
                    '03-2025': {
                        sales: '€2,200.00', orders: '90', top: 'Coke', avg: '€24.40', peak: '18:00-19:00'
                    },
                    '02-2025': {
                        sales: '€2,100.00', orders: '85', top: 'Wine', avg: '€24.70', peak: '21:00-22:00'
                    },
                    '01-2025': {
                        sales: '€1,900.00', orders: '80', top: 'Nachos', avg: '€23.80', peak: '20:00-21:00'
                    }
                };
                let key = month + '-' + year;
                let s = stats[key] || {sales:'N/A',orders:'N/A',top:'-',avg:'-',peak:'-'};
                prevMonthStats.innerHTML = `
                    <li>Sales for ${label}: <strong>${s.sales}</strong></li>
                    <li>Orders for ${label}: <strong>${s.orders}</strong></li>
                    <li>Top Product: <strong>${s.top}</strong></li>
                    <li>Average Order Value: <strong>${s.avg}</strong></li>
                    <li>Peak Hour: <strong>${s.peak}</strong></li>
                `;
                prevMonthDropdown.style.display = 'none';
            }
        });
    }

    // --- Last 7/30 Days Dropdowns ---
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
                let stats = {
                    '2': {sales:'€2,000.00',orders:'80',top:'Beer',avg:'€25.00',peak:'20:00-21:00'},
                    '7': {sales:'€7,890.00',orders:'312',top:'Beer',avg:'€25.30',peak:'21:00-22:00'},
                    '14': {sales:'€15,000.00',orders:'600',top:'Nachos',avg:'€25.00',peak:'20:00-21:00'},
                    '21': {sales:'€22,000.00',orders:'900',top:'Coke',avg:'€24.80',peak:'19:00-20:00'},
                    '30': {sales:'€32,000.00',orders:'1,200',top:'Wine',avg:'€26.00',peak:'20:00-21:00'}
                };
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
                let stats = {
                    '7': {sales:'€7,890.00',orders:'312',top:'Beer',avg:'€25.30',peak:'21:00-22:00'},
                    '14': {sales:'€15,000.00',orders:'600',top:'Nachos',avg:'€25.00',peak:'20:00-21:00'},
                    '30': {sales:'€32,450.00',orders:'1,245',top:'Nachos',avg:'€26.10',peak:'20:00-21:00'},
                    '60': {sales:'€60,000.00',orders:'2,400',top:'Beer',avg:'€25.80',peak:'19:00-20:00'},
                    '90': {sales:'€90,000.00',orders:'3,600',top:'Coke',avg:'€26.50',peak:'21:00-22:00'}
                };
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
        // Sales This Week (red)
        var ctx = document.getElementById('salesChart').getContext('2d');
        window.analyticsCharts['salesChart'] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (€)',
                    data: [123, 234, 180, 290, 210, 340, 270],
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.15)',
                    pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                    pointBorderColor: '#fff',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Sales This Week' },
                    legend: { display: false }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
        // Sales Last Week (blue)
        var ctxLastWeek = document.getElementById('salesLastWeekChart').getContext('2d');
        window.analyticsCharts['salesLastWeekChart'] = new Chart(ctxLastWeek, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (€)',
                    data: [110, 200, 150, 260, 180, 320, 250],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.15)',
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Sales Last Week' },
                    legend: { display: false }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
        // Sales Last Month (yellow)
        var ctxLastMonth = document.getElementById('salesLastMonthChart').getContext('2d');
        window.analyticsCharts['salesLastMonthChart'] = new Chart(ctxLastMonth, {
            type: 'line',
            data: {
                labels: Array.from({length: 30}, (_, i) => `Apr ${i+3}`),
                datasets: [{
                    label: 'Sales (€)',
                    data: [1000, 1100, 1200, 900, 950, 1050, 1200, 1300, 1100, 1250, 1400, 1350, 1200, 1150, 1300, 1400, 1450, 1500, 1550, 1600, 1650, 1700, 1750, 1800, 1850, 1900, 1950, 2000, 2050, 2100],
                        borderColor: 'rgba(255, 206, 86, 1)',
                        backgroundColor: 'rgba(255, 206, 86, 0.15)',
                        pointBackgroundColor: 'rgba(255, 206, 86, 1)',
                    pointBorderColor: '#fff',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Sales Last 30 Days' },
                    legend: { display: false }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
        // Product Bar Chart
        var ctx1 = document.getElementById('productBarChart').getContext('2d');
        var productLabels = ['Beer', 'Nachos', 'Coke', 'Whiskey', 'Gin', 'Rum', 'Vodka', 'Tequila', 'Brandy', 'Wine', 'Martini', 'Champagne', 'Absinthe', 'Mezcal', 'Vermouth', 'Port', 'Sherry', 'Grappa', 'Baileys', 'Campari'];
        var productRevenue = [2400, 1800, 1500, 40, 60, 200, 250, 100, 40, 300, 160, 120, 20, 80, 40, 60, 40, 60, 140, 100];
        var productOrders = [480, 320, 240, 8, 12, 40, 50, 20, 8, 60, 32, 24, 4, 16, 8, 12, 8, 12, 28, 20];
        window.analyticsCharts['productBarChart'] = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Revenue (Last 30 Days)',
                    data: productRevenue,
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
                        'rgba(99, 255, 132, 0.5)',
                        'rgba(255, 140, 0, 0.5)',
                        'rgba(255, 215, 0, 0.5)',
                        'rgba(124, 252, 0, 0.5)',
                        'rgba(0, 191, 255, 0.5)',
                        'rgba(255, 20, 147, 0.5)',
                        'rgba(210, 105, 30, 0.5)',
                        'rgba(128, 0, 128, 0.5)',
                        'rgba(0, 128, 128, 0.5)',
                        'rgba(255, 69, 0, 0.5)',
                        'rgba(0, 255, 255, 0.5)'
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
                        'rgba(99, 255, 132, 1)',
                        'rgba(255, 140, 0, 1)',
                        'rgba(255, 215, 0, 1)',
                        'rgba(124, 252, 0, 1)',
                        'rgba(0, 191, 255, 1)',
                        'rgba(255, 20, 147, 1)',
                        'rgba(210, 105, 30, 1)',
                        'rgba(128, 0, 128, 1)',
                        'rgba(0, 128, 128, 1)',
                        'rgba(255, 69, 0, 1)',
                        'rgba(0, 255, 255, 1)'
                    ],
                    borderWidth: 1,
                    customOrders: productOrders
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: 'All Products Revenue (Last 30 Days)' },
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                var value = context.parsed.y !== undefined ? context.parsed.y : context.parsed;
                                var orders = context.dataset.customOrders[context.dataIndex];
                                return label + ': €' + value + ' (Orders: ' + orders + ')';
                            }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        // --- Product Bar Chart Dropdown Logic ---
        var productBarChartRange = document.getElementById('productBarChartRange');
        // Data for each range
        var productBarChartData = {
            '30': {
                labels: ['Beer', 'Nachos', 'Coke', 'Whiskey', 'Gin', 'Rum', 'Vodka', 'Tequila', 'Brandy', 'Wine', 'Martini', 'Champagne', 'Absinthe', 'Mezcal', 'Vermouth', 'Port', 'Sherry', 'Grappa', 'Baileys', 'Campari'],
                data: [2400, 1800, 1500, 40, 60, 200, 250, 100, 40, 300, 160, 120, 20, 80, 40, 60, 40, 60, 140, 100],
                orders: [480, 320, 240, 8, 12, 40, 50, 20, 8, 60, 32, 24, 4, 16, 8, 12, 8, 12, 28, 20]
            },
            '7': {
                labels: ['Beer', 'Nachos', 'Coke', 'Whiskey', 'Gin', 'Rum', 'Vodka', 'Tequila', 'Brandy', 'Wine'],
                data: [560, 400, 350, 10, 15, 40, 50, 20, 8, 60],
                orders: [120, 80, 60, 2, 3, 10, 12, 5, 2, 15]
            },
            '1': {
                labels: ['Beer', 'Nachos', 'Coke', 'Whiskey', 'Gin', 'Rum', 'Vodka', 'Tequila', 'Brandy', 'Wine'],
                data: [80, 60, 50, 0, 1, 2, 3, 1, 0, 4],
                orders: [20, 10, 8, 0, 1, 2, 3, 1, 0, 4]
            }
        };
        var productBarChart = window.analyticsCharts['productBarChart'];
        if (productBarChartRange && productBarChart) {
            productBarChartRange.addEventListener('change', function() {
                var val = productBarChartRange.value;
                var d = productBarChartData[val];
                productBarChart.data.labels = d.labels;
                productBarChart.data.datasets[0].data = d.data;
                productBarChart.data.datasets[0].customOrders = d.orders;
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

        // Category Doughnut Chart (by Orders)
        var ctx2 = document.getElementById('categoryDoughnutChart').getContext('2d');
        window.analyticsCharts['categoryDoughnutChart'] = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Drinks', 'Snacks', 'Food', 'Cocktails', 'Desserts', 'Coffee', 'Tea', 'Wine', 'Beer', 'Appetizers'],
                datasets: [{
                    data: [520, 210, 180, 100, 40, 150, 110, 75, 200, 90],
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
        // Category Revenue Doughnut Chart (by Revenue)
        var ctx3 = document.getElementById('categoryRevenueDoughnutChart').getContext('2d');
        window.analyticsCharts['categoryRevenueDoughnutChart'] = new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: ['Drinks', 'Snacks', 'Food', 'Cocktails', 'Desserts', 'Coffee', 'Tea', 'Wine', 'Beer', 'Appetizers'],
                datasets: [{
                    data: [800, 300, 134, 220, 60, 210, 120, 180, 400, 150], // Example revenue data
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

    // --- Table, QR, Staff Charts ---
    if (window.Chart) {
        // Table Pie Chart
        var tablePieChartElem = document.getElementById('tablePieChart');
        if (tablePieChartElem) {
            window.analyticsCharts['tablePieChart'] = new Chart(tablePieChartElem.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5'],
                    datasets: [{
                        label: 'Table Usage',
                        data: [5, 8, 3, 2, 18],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
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
        // Example data for each range
        var tablePieChartData = {
            'all': {
                labels: ['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5'],
                data: [50, 80, 30, 20, 180]
            },
            'month': {
                labels: ['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5'],
                data: [20, 30, 10, 8, 60]
            },
            'week': {
                labels: ['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5'],
                data: [5, 8, 3, 2, 18]
            },
            'day': {
                labels: ['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5'],
                data: [1, 2, 0, 0, 5]
            }
        };
        if (tablePieChartRange && tablePieChart) {
            tablePieChartRange.addEventListener('change', function() {
                var val = tablePieChartRange.value;
                var d = tablePieChartData[val];
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
