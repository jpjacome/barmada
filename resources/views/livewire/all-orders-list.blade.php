<div class="orders-container">
    <link href="{{ asset('css/all-orders.css') }}" rel="stylesheet">
    
    <div class="orders-main py-4">
        <div class="orders-header">
            <h3 class="orders-title">All Orders</h3>
            <div class="orders-actions">
                <div class="orders-status" wire:poll.5s="refreshPendingOrders">Last updated: {{ $lastUpdated }}</div>
            </div>
        </div>
        
        <div class="orders-data mt-4">
            <!-- Pending Orders Panel -->
            @if(count($pendingOrders) > 0)
            <div class="orders-panel">
                <div class="orders-panel-content">
                    <h3 class="orders-panel-title">Pending Orders</h3>
                    
                    <div class="orders-scroll-container" style="overflow-x: auto; white-space: nowrap;">
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
                                                $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='w-4 h-4 inline-block'>";
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
                                
                                <div class="order-card-time">
                                    <div class="order-time-label">Created:</div>
                                    <div class="order-created-time">{{ \Carbon\Carbon::parse($pendingOrder['created_at'])->format('H:i:s') }}</div>
                                    <div class="chronometer">00:00</div>
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
                    </div>
                </div>
            </div>
            @endif

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
                                            class="order-status-badge order-status-{{ $order->status }} cursor-pointer hover:opacity-80"
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
                                                        $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='w-4 h-4 inline-block'>";
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
                                                <svg xmlns="http://www.w3.org/2000/svg" class="orders-action-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button 
                                                wire:click="deleteOrder({{ $order->id }})" 
                                                class="orders-action-button orders-delete-button" 
                                                title="Delete Order"
                                                onclick="return confirm('Are you sure you want to delete this order?')"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="orders-action-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" class="orders-action-icon mr-1" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Save XML
            </button>
        </div>
        
        <!-- Bottom notification -->
        @if(session()->has('message'))
            <div class="orders-notification">
                <div class="orders-notification-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="orders-notification-icon" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="orders-modal-body">
                    <div class="orders-form-group">
                        <label class="orders-form-label">Current Status</label>
                        <div class="orders-status-display">
                            <button 
                                wire:click="toggleStatusInModal" 
                                class="order-status-badge order-status-{{ $editingOrder['status'] ?? 'pending' }} cursor-pointer hover:opacity-80"
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