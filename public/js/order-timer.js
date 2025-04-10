class OrderTimer {
    constructor(element) {
        this.element = element;
        this.createdAt = new Date(element.dataset.createdAt);
        this.status = element.dataset.status;
        this.updateInterval = null;
        this.start();
    }

    update() {
        if (this.status === 'pending') {
            const now = new Date();
            const diff = Math.floor((now - this.createdAt) / 1000);
            const minutes = Math.floor(diff / 60);
            const seconds = diff % 60;
            
            this.element.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Add warning class if more than 5 minutes
            if (diff >= 300) {
                this.element.classList.add('chronometer-warning');
            }
        } else {
            this.element.textContent = '';
            this.element.classList.remove('chronometer-warning');
        }
    }

    start() {
        this.update();
        this.updateInterval = setInterval(() => this.update(), 1000);
    }

    stop() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
}

// Initialize all timers on the page
document.addEventListener('DOMContentLoaded', () => {
    initializeTimers();
});

// Handle dynamic content updates
document.addEventListener('livewire:initialized', () => {
    initializeTimers();
});

// Handle Livewire updates
document.addEventListener('livewire:update', () => {
    initializeTimers();
});

function initializeTimers() {
    // Stop all existing timers
    if (window.orderTimers) {
        window.orderTimers.forEach(timer => timer.stop());
    }
    
    // Initialize new timers
    const timerElements = document.querySelectorAll('[data-timer]');
    window.orderTimers = Array.from(timerElements).map(element => {
        return new OrderTimer(element);
    });
} 