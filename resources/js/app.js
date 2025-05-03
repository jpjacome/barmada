import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

let salesChartInstance = null;

function renderSalesChart() {
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        if (salesChartInstance) {
            salesChartInstance.destroy();
        }
        salesChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales (Random €)',
                    data: [123, 234, 180, 290, 210, 340, 270],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true },
                    title: { display: true, text: 'Sales This Week' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
}

window.addEventListener('livewire:load', renderSalesChart);
window.addEventListener('livewire:navigated', renderSalesChart);
window.addEventListener('livewire:update', renderSalesChart);

console.log('App.js loaded!');
