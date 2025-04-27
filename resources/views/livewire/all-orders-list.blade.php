<div class="orders-container">
    <link href="{{ asset('css/all-orders.css') }}" rel="stylesheet">
    
    <div class="orders-main">
        
        <div class="orders-data">
            <!-- Pending Orders Panel -->
            <div class="orders-panel">
                <h3 class="orders-panel-title">Pending Orders</h3>
                <div class="orders-panel-content" wire:poll.5s="refreshPendingOrders">
                    <div class="orders-scroll-container">
                        @if(count($pendingOrders) > 0)
                            @foreach($pendingOrders as $pendingOrder)
                                <div class="order-card" 
                                     data-created-at="{{ $pendingOrder['created_at'] }}"
                                     data-order-id="{{ $pendingOrder['id'] }}"
                                     wire:key="card-{{ $pendingOrder['id'] }}"
                                     wire:ignore>
                                    <div class="order-card-header">
                                        <h4 class="order-card-title">Order #{{ $pendingOrder['id'] }}</h4>
                                        <span class="order-card-table" wire:key="table-{{ $pendingOrder['id'] }}">Table {{ $pendingOrder['table']['id'] }}</span>
                                    </div>
                                    
                                    <div class="order-card-body">
                                        <div class="order-card-time">
                                            <span class="order-time-label">Created:</span>
                                            <span class="order-created-time">{{ \Carbon\Carbon::parse($pendingOrder['created_at'])->format('H:i:s') }}</span>
                                            <span class="chronometer">00:00</span>
                                        </div>
                                        <div class="order-card-products" wire:key="products-{{ $pendingOrder['id'] }}">
                                            @php
                                                $productList = [];
                                                $order = \App\Models\Order::with('items.product')->find($pendingOrder['id']);
                                                $groupedItems = [];
                                                
                                                // Group items by product
                                                foreach ($order->items as $item) {
                                                    if (!isset($groupedItems[$item->product_id])) {
                                                        $groupedItems[$item->product_id] = [
                                                            'product' => $item->product,
                                                            'quantity' => 0
                                                        ];
                                                    }
                                                    $groupedItems[$item->product_id]['quantity'] += $item->quantity;
                                                }
                                                
                                                // Create product list with grouped quantities
                                                foreach ($groupedItems as $item) {
                                                    $icon = $item['product']->icon_value ?? 'bi-box';
                                                    $iconType = $item['product']->icon_type ?? 'bootstrap';
                                                    
                                                    if ($iconType === 'bootstrap') {
                                                        $iconHtml = "<i class='{$icon}'></i>";
                                                    } else {
                                                        $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='order-product-icon'>";
                                                    }
                                                    
                                                    $productList[] = "<span class='order-product-item'>{$iconHtml} <span class='order-product-name'>{$item['product']->name}</span>: {$item['quantity']}</span>";
                                                }
                                            @endphp
                                            
                                            @if(count($productList) > 0)
                                                {!! implode(', ', $productList) !!}
                                            @else
                                                <span class="order-no-products">No products</span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="order-card-footer">
                                        <div class="order-card-actions">
                                            <button 
                                                wire:click="toggleStatus({{ $pendingOrder['id'] }})" 
                                                class="order-status-button order-status-pending"
                                                wire:loading.attr="disabled"
                                                wire:key="status-{{ $pendingOrder['id'] }}"
                                            >
                                                Pending
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="no-orders-message">No pending orders at the moment.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="orders-table-container">
                <div class="orders-table-wrapper">
                    <table class="orders-table">
                        <thead class="orders-table-head">
                            <tr>
                                <th wire:click="sortBy('id')" class="orders-table-cell orders-sort-header">
                                    <div class="orders-sort-wrapper">
                                        <span>ID</span>
                                        @if($sort === 'id')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="orders-sort-icon orders-sort-{{ $direction === 'asc' ? 'asc' : 'desc' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('table_id')" class="orders-table-cell orders-sort-header">
                                    <div class="orders-sort-wrapper">
                                        <span>Table</span>
                                        @if($sort === 'table_id')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="orders-sort-icon orders-sort-{{ $direction === 'asc' ? 'asc' : 'desc' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('status')" class="orders-table-cell orders-sort-header">
                                    <div class="orders-sort-wrapper">
                                        <span>Status</span>
                                        @if($sort === 'status')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="orders-sort-icon orders-sort-{{ $direction === 'asc' ? 'asc' : 'desc' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th wire:click="sortBy('created_at')" class="orders-table-cell orders-sort-header">
                                    <div class="orders-sort-wrapper">
                                        <span>Created</span>
                                        @if($sort === 'created_at')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="orders-sort-icon orders-sort-{{ $direction === 'asc' ? 'asc' : 'desc' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="orders-table-cell">Items</th>
                                <th class="orders-table-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="orders-table-body">
                            @forelse($allOrders as $order)
                                <tr class="orders-table-row">
                                    <td class="orders-table-cell">{{ $order->id }}</td>
                                    <td class="orders-table-cell">Table {{ $order->table->id }}</td>
                                    <td class="orders-table-cell">
                                        <button 
                                            wire:click="toggleStatus({{ $order->id }})" 
                                            class="order-status-badge order-status-{{ $order->status }}"
                                        >
                                            {{ ucfirst($order->status) }}
                                        </button>
                                    </td>
                                    <td class="orders-table-cell">{{ $order->created_at->format('M d, Y H:i') }}</td>
                                    <td class="orders-table-cell">
                                        <div class="orders-product-list">
                                            @php
                                                $productList = [];
                                                $order = \App\Models\Order::with('items.product')->find($order->id);
                                                foreach ($order->items as $item) {
                                                    $icon = $item->product->icon_value ?? 'bi-box';
                                                    $iconType = $item->product->icon_type ?? 'bootstrap';
                                                    
                                                    if ($iconType === 'bootstrap') {
                                                        $iconHtml = "<i class='{$icon}'></i>";
                                                    } else {
                                                        $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='order-product-icon'>";
                                                    }
                                                    
                                                    $productList[] = "<span class='order-product-item'>{$iconHtml} <span class='order-product-name'>{$item->product->name}</span>: {$item->quantity}</span>";
                                                }
                                            @endphp
                                            
                                            @if(count($productList) > 0)
                                                {!! implode(', ', $productList) !!}
                                            @else
                                                <span class="order-no-products">No products</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="orders-table-cell">
                                        <div class="orders-actions">
                                            <button 
                                                wire:click="openStatusModal({{ $order->id }})" 
                                                class="orders-action-button orders-edit-button" 
                                                title="Edit Status"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button 
                                                wire:click="deleteOrder({{ $order->id }})" 
                                                class="orders-action-button orders-delete-button" 
                                                title="Delete Order"
                                                onclick="return confirm('Are you sure you want to delete this order?')"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="orders-empty-message">No orders found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="orders-footer">
        <div class="orders-footer-actions">
            <a href="#" wire:click.prevent="deleteAllOrders" class="orders-erase-all" onclick="return confirm('Are you sure you want to delete ALL orders? This action cannot be undone.')">
                Erase all
            </a>
            <button 
                wire:click="exportOrdersAsXml" 
                class="orders-export-button-footer" 
                title="Export Orders as XML"
            >
                <i class="bi bi-download"></i>
                Save XML
            </button>
        </div>
        
        <!-- Bottom notification -->
        @if(session()->has('message'))
            <div class="orders-notification">
                <div class="orders-notification-content">
                    <i class="bi bi-check-circle"></i>
                    <span>{{ session('message') }}</span>
                </div>
            </div>
        @endif
    </div>

    <!-- Status Modal -->
    @if($showStatusModal)
        <div class="orders-modal-overlay">
            <div class="orders-modal">
                <div class="orders-modal-header">
                    <h3 class="orders-modal-title">Change Order Status</h3>
                    <button wire:click="closeModal" class="orders-modal-close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                
                <div class="orders-modal-body">
                    <div class="orders-form-group">
                        <label class="orders-form-label">Current Status</label>
                        <div class="orders-status-display">
                            <button 
                                wire:click="toggleStatusInModal" 
                                class="order-status-badge order-status-{{ $editingOrder['status'] ?? 'pending' }}"
                            >
                                {{ ucfirst($editingOrder['status'] ?? 'pending') }}
                            </button>
                        </div>
                    </div>
                    
                    <div class="orders-form-group">
                        <label class="orders-form-label">Table</label>
                        <select 
                            wire:model="editingOrder.table_id"
                            class="orders-form-select"
                        >
                            @foreach($tables as $table)
                                <option value="{{ $table->id }}" {{ $editingOrder['table_id'] == $table->id ? 'selected' : '' }}>
                                    Table {{ $table->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="orders-form-group">
                        <label class="orders-form-label">Products</label>
                        <div class="orders-products-grid">
                            @foreach($products as $product)
                                <div class="orders-product-card">
                                    <div class="orders-product-header">
                                        @if($product['icon_type'] === 'bootstrap')
                                            <i class="{{ $product['icon_value'] }}"></i>
                                        @else
                                            <img src="{{ asset('storage/' . $product['icon_value']) }}" alt="{{ $product['name'] }}" class="orders-product-icon">
                                        @endif
                                        <span class="orders-product-name">{{ $product['name'] }}</span>
                                    </div>
                                    <div class="orders-product-controls">
                                        <button 
                                            wire:click="decrementProductQuantity({{ $product['id'] }})"
                                            class="orders-product-button orders-product-decrease"
                                            @if(($editingOrder['products'][$product['id']] ?? 0) <= 0) disabled @endif
                                        >
                                            -
                                        </button>
                                        <input 
                                            type="number" 
                                            value="{{ $editingOrder['products'][$product['id']] ?? 0 }}"
                                            wire:change="updateProductQuantity({{ $product['id'] }}, $event.target.value)"
                                            class="orders-product-input"
                                            min="0"
                                        >
                                        <button 
                                            wire:click="incrementProductQuantity({{ $product['id'] }})"
                                            class="orders-product-button orders-product-increase"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div class="orders-modal-footer">
                    <button type="button" wire:click="closeModal" class="orders-modal-cancel">
                        Cancel
                    </button>
                    <button wire:click="saveChanges" class="orders-modal-save">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
// Create a separate namespace for our timer functionality
const OrderTimer = {
    cards: new Map(),
    
    init() {
        this.updateAllChronometers();
        setInterval(() => this.updateAllChronometers(), 1000);
        
        // Listen for Livewire updates
        Livewire.on('orderDetailsUpdated', (changes) => {
            Object.entries(changes).forEach(([orderId, changeType]) => {
                if (changeType === 'removed') {
                    this.cards.delete(orderId);
                }
            });
        });

        // Listen for refresh event
        Livewire.on('refresh-orders', () => {
            // Reinitialize the timer for all cards
            this.cards.clear();
            this.updateAllChronometers();
        });
    },
    
    updateAllChronometers() {
        const now = new Date();
        document.querySelectorAll('.order-card').forEach(card => {
            const orderId = card.dataset.orderId;
            const createdAt = new Date(card.dataset.createdAt);
            const elapsedMs = now - createdAt;
            const elapsedMinutes = Math.floor(elapsedMs / 60000);
            const elapsedSeconds = Math.floor((elapsedMs % 60000) / 1000);
            
            const chronometer = card.querySelector('.chronometer');
            chronometer.textContent = `${String(elapsedMinutes).padStart(2, '0')}:${String(elapsedSeconds).padStart(2, '0')}`;
            
            // Update warning state
            const isWarning = elapsedMinutes >= 5;
            const currentState = this.cards.get(orderId);
            
            if (!currentState || currentState.isWarning !== isWarning) {
                if (isWarning) {
                    card.classList.add('order-card-warning');
                    chronometer.classList.add('chronometer-warning');
                } else {
                    card.classList.remove('order-card-warning');
                    chronometer.classList.remove('chronometer-warning');
                }
                this.cards.set(orderId, { isWarning });
            }
        });
    }
};

// Initialize when the page loads
document.addEventListener('livewire:initialized', () => OrderTimer.init());
</script>
@endpush