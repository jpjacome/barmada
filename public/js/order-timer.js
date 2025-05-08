// JavaScript Order Chronometer and Warning Logic
// Updates all .chronometer elements every second, adds/removes warning classes as needed

document.addEventListener('DOMContentLoaded', function () {
    function updateChronometers() {
        const now = new Date();
        document.querySelectorAll('.chronometer[data-created-at]').forEach(function (el) {
            const createdAt = new Date(el.getAttribute('data-created-at'));
            const status = el.getAttribute('data-status');
            const card = el.closest('.order-card');
            if (status === 'pending') {
                const diffMs = now - createdAt;
                const h = String(Math.floor(diffMs / 3600000)).padStart(2, '0');
                const m = String(Math.floor((diffMs % 3600000) / 60000)).padStart(2, '0');
                const s = String(Math.floor((diffMs % 60000) / 1000)).padStart(2, '0');
                el.textContent = `${h}:${m}:${s}`;
                if ((Math.floor(diffMs / 60000)) >= 5) {
                    el.classList.add('chronometer-warning');
                    if (card) card.classList.add('order-card-warning');
                } else {
                    el.classList.remove('chronometer-warning');
                    if (card) card.classList.remove('order-card-warning');
                }
            } else {
                el.textContent = '00:00:00';
                el.classList.remove('chronometer-warning');
                if (card) card.classList.remove('order-card-warning');
            }
        });
    }
    setInterval(updateChronometers, 1000);
    updateChronometers();
});