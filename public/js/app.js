// Compiled version of app.js for direct browser use
// No ES module syntax

console.log('Barmada app.js loading from', window.location.href);

// Simple approach - use relative paths since they're working
window.BARMADA_BASE_URL = '.';
console.log('Using relative URLs for assets');

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Alpine.js (which is already included via CDN)
    if (window.Alpine) {
        console.log('Alpine detected in app.js');
        // Don't start Alpine here as Livewire will initialize it
        // window.Alpine.start();
    }

    // Add any other functionality needed for the app
    console.log('Barmada app initialized successfully');
    
    // Set up CSRF token for AJAX requests
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        window.axios = {
            defaults: {
                headers: {
                    'X-CSRF-TOKEN': token
                }
            }
        };
    }
    
    // Event listeners for interactive elements
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('data-target'));
            if (target) {
                target.classList.toggle('show');
            }
        });
    });
});

// Get base URL for the app - simplified to use relative paths
function getBaseUrl() {
    return '.';
}

// Order-related functionality
window.updateOrderStatus = function(orderId, status) {
    const url = `orders/${orderId}`;
    
    fetch(url, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Order updated:', data);
        if (data.success) {
            // Refresh page or update UI as needed
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error updating order:', error);
    });
};

// Theme toggling
window.toggleTheme = function() {
    fetch(`settings/toggle-theme`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}; 