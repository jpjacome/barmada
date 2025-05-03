
<div class="orders-container">
    <!-- Link to the external CSS file -->
    <link href="{{ asset('css/orders.css') }}" rel="stylesheet">
    
    <div class="orders-main py-4">
        <div class="orders-header">
            <h3 class="orders-title">Orders</h3>
            <div class="orders-actions">
                <div class="orders-status">Last updated: {{ $lastUpdated }}</div>
                <a href="{{ route('orders.create') }}" class="orders-create-button">
                    New Order
                </a>
            </div>
        </div>
        
        <div 
            wire:poll.{{ $refreshInterval }}s="refreshOrders"
            class="orders-data mt-4"
        >
            <!-- Latest Orders Display -->
            @if(count($pendingOrders) > 0)
            <div class="orders-panel">
                <div class="orders-panel-content">
                    <h3 class="orders-panel-title">Latest Orders</h3>
                    
                    <div class="orders-scroll-container">
                        @foreach($pendingOrders as $pendingOrder)
                        <div class="order-card {{ strtotime($pendingOrder['created_at']) < (time() - 300) ? 'order-card-warning' : '' }}">
                            <div class="order-card-header">
                                <h4 class="order-card-title">Order #{{ $pendingOrder['id'] }}</h4>
                                <span class="order-card-table">Table {{ $pendingOrder['table']['id'] }}</span>
                            </div>
                            
                            <div class="order-card-body">
                                <div class="order-card-products">
                                    @php
                                        $productList = [];
                                        for ($i = 1; $i <= 9; $i++) {
                                            $qty = $pendingOrder["product{$i}_qty"] ?? 0;
                                            if ($qty > 0 && isset($products[$i])) {
                                                $icon = $products[$i]['icon_value'] ?? 'bi-box';
                                                $iconType = $products[$i]['icon_type'] ?? 'bootstrap';
                                                
                                                if ($iconType === 'bootstrap') {
                                                    $iconHtml = "<i class='{$icon}'></i>";
                                                } else {
                                                    $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='w-4 h-4 inline-block'>";
                                                }
                                                
                                                $productList[] = "<span class='order-product-item'>{$iconHtml} <span class='order-product-name'>{$products[$i]['name']}</span>: {$qty}</span>";
                                            }
                                        }
                                    @endphp
                                    
                                    @if(count($productList) > 0)
                                        {!! implode(', ', $productList) !!}
                                    @else
                                        <span class="order-no-products">No products</span>
                                    @endif
                                </div>
                                
                                <div class="order-card-time">
                                    <div class="order-time-label">Time:</div>
                                    <livewire:order-timer 
                                        :created-at="$pendingOrder['created_at']" 
                                        :status="$pendingOrder['status']" 
                                        :key="'pending-timer-'.$pendingOrder['id']" 
                                    />
                                </div>
                            </div>
                            
                            <div class="order-card-footer">
                                <div class="order-card-actions">
                                    <button 
                                        wire:click="updateOrderStatus({{ $pendingOrder['id'] }}, 'delivered')" 
                                        class="order-status-button order-status-pending"
                                    >
                                        Pending
                                    </button>
                                    <button 
                                        wire:click="editOrder({{ $pendingOrder['id'] }})" 
                                        @click="$dispatch('open-edit-modal')"
                                        class="order-edit-button" 
                                        title="Edit Order"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="order-action-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
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
                <div class="orders-table-header">
                    <h3 class="orders-table-title">All Orders</h3>
                </div>
                
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
                                        <span class="order-status-badge order-status-{{ $order->status }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="orders-table-cell">{{ $order->created_at->format('M d, Y H:i') }}</td>
                                    <td class="orders-table-cell">
                                        <div class="orders-product-list">
                                            @php
                                                $productList = [];
                                                foreach ($order->items as $item) {
                                                    $icon = $products[$item->product_id]['icon_value'] ?? 'bi-box';
                                                    $iconType = $products[$item->product_id]['icon_type'] ?? 'bootstrap';
                                                    
                                                    if ($iconType === 'bootstrap') {
                                                        $iconHtml = "<i class='{$icon}'></i>";
                                                    } else {
                                                        $iconHtml = "<img src='" . asset('storage/' . $icon) . "' class='w-4 h-4 inline-block'>";
                                                    }
                                                    
                                                    $productList[] = "<span class='order-product-item'>{$iconHtml} <span class='order-product-name'>{$products[$item->product_id]['name']}</span>: {$item->quantity}</span>";
                                                }
                                            @endphp
                                            
                                            @if(count($productList) > 0)
                                                {!! implode(', ', $productList) !!}
                                            @else
                                                <span class="text-gray-500">No items</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="orders-table-cell">
                                        <div class="orders-actions">
                                            @if($order->status == 'pending')
                                                <button 
                                                    wire:click="updateOrderStatus({{ $order->id }}, 'delivered')" 
                                                    class="orders-action-button orders-deliver-button" 
                                                    title="Mark as Delivered"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="orders-action-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            @endif
                                            <button 
                                                wire:click="editOrder({{ $order->id }})" 
                                                @click="$dispatch('open-edit-modal')"
                                                class="orders-action-button orders-edit-button" 
                                                title="Edit Order"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="orders-action-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="orders-empty-message">No orders found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="orders-footer">
        <a href="{{ route('dashboard') }}" class="orders-back-link">Back to Dashboard</a>
    </div>

    <!-- Edit Order Modal -->
    <div x-data="{ show: @entangle('showEditModal') }" 
         x-show="show" 
         x-cloak
         class="orders-modal-overlay"
    >
        <div class="orders-modal" @click.away="show = false">
            <div class="orders-modal-header">
                <h3 class="orders-modal-title">Edit Order #{{ $editingOrder['id'] ?? '' }}</h3>
                <button @click="show = false" class="orders-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form wire:submit.prevent="updateOrder">
                <div class="orders-modal-body">
                    <!-- Table Selection -->
                    <div class="orders-form-group">
                        <label for="edit_table_id" class="orders-form-label">Table</label>
                        <select 
                            id="edit_table_id" 
                            wire:model="editingOrder.table_id" 
                            class="orders-form-select"
                        >
                            <option value="">Select a table</option>
                            @foreach($tables as $table)
                                <option value="{{ $table->id }}" {{ isset($editingOrder['table_id']) && $editingOrder['table_id'] == $table->id ? 'selected' : '' }}>
                                    Table {{ $table->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Products List -->
                    <div class="orders-form-group">
                        <label class="orders-form-label">Products</label>
                        <div class="orders-product-grid">
                            @foreach($products as $product)
                                <div class="orders-product-item">
                                    <div class="orders-product-info">
                                        @if($product['icon_type'] === 'bootstrap')
                                            <i class="{{ $product['icon_value'] ?? 'bi-box' }} text-blue-600"></i>
                                        @else
                                            <img src="{{ asset('storage/' . $product['icon_value']) }}" alt="{{ $product['name'] }}" class="w-6 h-6 object-contain">
                                        @endif
                                        <span class="orders-product-name">{{ $product['name'] }}</span>
                                        <span class="orders-product-price">${{ number_format($product['price'], 2) }}</span>
                                    </div>
                                    <input 
                                        type="number" 
                                        wire:model="editingOrder.products.{{ $product['id'] }}" 
                                        min="0" 
                                        class="orders-product-input"
                                        value="{{ isset($editingOrder['products'][$product['id']]) ? $editingOrder['products'][$product['id']] : 0 }}"
                                    >
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div class="orders-modal-footer">
                    <button type="button" @click="show = false" class="orders-modal-cancel">
                        Cancel
                    </button>
                    <button type="submit" class="orders-modal-save">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div> 