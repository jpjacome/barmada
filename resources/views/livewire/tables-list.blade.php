<div class="tables-container {{ $showOrdersModal ? 'modal-open' : '' }}">
    
    
    <style>
        @import url('{{ asset('css/tables-list.css') }}');
    </style>
    
    <!-- Tables Grid -->
    <div 
        wire:poll.{{ $refreshInterval }}s="refreshTables"
        class="tables-grid"
    >
        @forelse ($tables as $table)
            <div 
                class="table-card {{ $table->status === 'closed' ? 'fully-paid' : '' }}"
                wire:key="table-{{ $table->id }}"
            >
                <div class="table-card-header">
                    <h4 class="table-card-title">Table {{ $table->table_number ?? $table->id }}</h4>
                    <span class="table-status status-{{ $table->status }}"
                          style="cursor:pointer;"
                          wire:click="toggleTableStatus({{ $table->id }})"
                          title="Click to change table status">
                        @if($table->status === 'pending_approval')
                            Pending Approval
                        @else
                            {{ ucfirst($table->status) }}
                        @endif
                    </span>
                </div>
                
                <div class="table-card-info" wire:key="info-{{ $table->id }}">
                    @if($editingReference === $table->id)
                        <div class="reference-edit-form">
                            <input
                                type="text"
                                wire:model="referenceText"
                                class="reference-input"
                                placeholder="Enter table reference..."
                                @keydown.enter="$wire.saveReference({{ $table->id }})"
                                @keydown.escape="$wire.cancelEditingReference()"
                                x-init="$nextTick(() => $el.focus())"
                            >
                            <div class="reference-actions">
                                <button 
                                    wire:click="saveReference({{ $table->id }})"
                                    class="reference-save"
                                >
                                    Save
                                </button>
                                <button 
                                    wire:click="cancelEditingReference"
                                    class="reference-cancel"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    @else
                        <div 
                            class="reference-display"
                            wire:click="startEditingReference({{ $table->id }})"
                        >
                            {{ $table->reference ?: 'Click to add reference...' }}
                        </div>
                    @endif
                </div>
                
                <div class="table-card-footer">
                    <div class="table-card-actions">
                        <button 
                            wire:click="deleteTable({{ $table->id }})" 
                            class="table-delete-button"
                            onclick="return confirm('Are you sure you want to delete this table?')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <button 
                            class="table-qr-button" 
                            wire:click="openQrModal({{ $table->id }}, {{ $table->table_number ?? $table->id }})"
                            title="Show QR Code"
                        >
                            <i class="bi bi-qr-code"></i>
                        </button>
                        <a href="{{ url('/qr-entry/' . rawurlencode($editorName) . '/' . $table->table_number) }}" class="table-card-button" target="_blank">
                            <i class="bi bi-plus-circle"></i> New Order
                        </a>
                    </div>
                    
                    <button 
                        wire:click="viewTableOrders({{ $table->id }})" 
                        class="table-view-button"
                    >
                        View Orders
                    </button>
                </div>
            </div>
        @empty
            <div class="tables-empty">
                No tables have been added yet. Add your first table to get started!
            </div>
        @endforelse
    </div>
    
    <!-- Add Table Button -->
    <div class="tables-add-button-container">
        <button wire:click="toggleAddForm" class="tables-add-button">
            <svg xmlns="http://www.w3.org/2000/svg" class="tables-add-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ $showAddForm ? 'Cancel' : 'Add New Table' }}
        </button>
    </div>
    
    <!-- Add Table Form -->
    @if($showAddForm)
    <div class="tables-add-form">
        <h4 class="tables-add-form-title">Add New Table</h4>
        <div class="tables-add-form-description">
            Click "Create Table" to add a new table to your venue.
        </div>
        <div class="tables-add-form-actions">
            <button wire:click="addTable" class="tables-add-form-button">
                Create Table
            </button>
        </div>
    </div>
    @endif
    
    <!-- Error Modal -->
    @if($showErrorModal)
        <div class="tables-modal-backdrop" wire:click="closeErrorModal">
            <div class="tables-modal" wire:click.stop>
                <div class="tables-modal-header">
                    <h3 class="tables-modal-title">Cannot Delete Table</h3>
                    <button class="tables-modal-close" wire:click="closeErrorModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="tables-modal-body">
                    <p class="text-gray-700">{{ $errorMessage }}</p>
                </div>
                <div class="tables-modal-footer">
                    <button class="tables-modal-button" wire:click="closeErrorModal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
    
    <!-- Table Orders Modal -->
    @if($showOrdersModal)
    <div class="modal-wrapper">
        <div 
            class="modal-backdrop" 
            wire:poll="refreshModal"
            wire:click="closeOrdersModal"
        >
            <div class="modal" wire:click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">Orders for Table {{ $tables->firstWhere('id', $selectedTable)->table_number ?? $selectedTable }}</h3>
                    <button wire:click="closeOrdersModal" class="modal-close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="modal-body">
                    @if(count($tableOrders) > 0)
                        @foreach($tableOrders as $order)
                            <div class="order-card" id="order-{{ $order['id'] }}">
                                <div class="order-header">
                                    <span class="order-id">Order #{{ $order['id'] }}</span>
                                    <span class="order-products-summary">
                                        @php
                                            $productSummary = [];
                                            foreach($order['items'] as $item) {
                                                if (!isset($productSummary[$item['product']['name']])) {
                                                    $productSummary[$item['product']['name']] = 0;
                                                }
                                                $productSummary[$item['product']['name']] += 1;
                                            }
                                            $summaryText = [];
                                            foreach($productSummary as $name => $quantity) {
                                                $summaryText[] = "{$name} ({$quantity})";
                                            }
                                        @endphp
                                        {{ implode(', ', $summaryText) }}
                                    </span>
                                    <span class="order-time">{{ \Carbon\Carbon::parse($order['created_at'])->format('H:i:s') }}</span>
                                    <button 
                                        class="order-toggle-products {{ $this->isOrderProductsVisible($order['id']) ? 'rotated' : '' }}"
                                        wire:click="toggleOrderProducts({{ $order['id'] }})"
                                    >
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                                
                                <div class="order-card-products" wire:key="products-{{ $order['id'] }}" style="display: {{ $this->isOrderProductsVisible($order['id']) ? 'flex' : 'none' }}">
                                    @foreach($order['items'] as $item)
                                        <div class="product-item {{ $this->isItemSelected($order['id'], $item['product_id'], $item['item_index']) ? 'selected' : '' }}"
                                             wire:click="selectItem({{ $order['id'] }}, {{ $item['product_id'] }}, {{ $item['item_index'] }})">
                                            <div class="product-item-content">
                                                <div class="product-item-left">
                                                    <i class="{{ $item['product']['icon_type'] === 'svg' ? 'bi ' . $item['product']['icon_value'] : $item['product']['icon_value'] }}"></i>
                                                    <span class="product-name">{{ $item['product']['name'] }}</span>
                                                    <span class="product-unit">#{{ $item['item_index'] }}</span>
                                                </div>
                                                <div class="product-item-right">
                                                    <span class="product-price">€{{ number_format($item['price'], 2) }}</span>
                                                </div>
                                            </div>
                                            <div class="product-item-status">
                                                <i class="fas {{ $this->isItemSelected($order['id'], $item['product_id'], $item['item_index']) ? 'fa-check-circle' : 'fa-circle' }}"></i>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <div class="order-footer">
                                    <div class="order-totals">
                                        <span>Total: €{{ number_format($order['total_amount'], 2) }}</span>
                                        <span>Paid: €{{ number_format($order['amount_paid'], 2) }}</span>
                                        <span>Left: €{{ number_format($order['amount_left'], 2) }}</span>
                                    </div>
                                    <div class="order-actions">
                                        <button 
                                            class="order-action-button"
                                            wire:click="toggleAllItems({{ $order['id'] }})"
                                        >
                                            @php
                                                $allPaid = collect($order['items'])->every(function ($item) {
                                                    return $item['is_paid'];
                                                });
                                            @endphp
                                            {{ $allPaid ? 'Mark as Unpaid' : 'Mark as Paid' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="order-empty">
                            No orders found for this table.
                        </div>
                    @endif
                </div>
                
                <div class="modal-footer">
                    @if(count($tableOrders) > 0)
                        @php
                            $tableTotal = 0;
                            $tablePaid = 0;
                            $tableLeft = 0;
                            foreach($tableOrders as $order) {
                                $tableTotal += $order['total_amount'];
                                $tablePaid += $order['amount_paid'];
                                $tableLeft += $order['amount_left'];
                            }
                        @endphp
                        <div class="footer-summary">
                            <div class="footer-summary-item">
                                <span class="footer-summary-label">Table Total:</span>
                                <span class="footer-summary-value">€{{ number_format($tableTotal, 2) }}</span>
                            </div>
                            <div class="footer-summary-item">
                                <span class="footer-summary-label">Total Paid:</span>
                                <span class="footer-summary-value footer-summary-paid">€{{ number_format($tablePaid, 2) }}</span>
                            </div>
                            <div class="footer-summary-item">
                                <span class="footer-summary-label">Total Left:</span>
                                <span class="footer-summary-value">€{{ number_format($tableLeft, 2) }}</span>
                            </div>
                        </div>
                        <div class="footer-actions">
                            <button 
                                class="modal-button"
                                wire:click="toggleAllTableItems"
                            >
                                @php
                                    $allPaid = collect($tableOrders)->every(function ($order) {
                                        return collect($order['items'])->every(function ($item) {
                                            return $item['is_paid'];
                                        });
                                    });
                                @endphp
                                {{ $allPaid ? 'Mark All as Unpaid' : 'Mark All as Paid' }}
                            </button>
                            <button wire:click="closeOrdersModal" class="modal-button">
                                Close
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- QR Modal -->
    @if($showQrModal)
        <div class="modal-wrapper">
            <div class="modal-backdrop" wire:click="closeQrModal">
                <div class="modal" wire:click.stop>
                    <div class="modal-header">
                        <h3 class="modal-title">QR for Table {{ $qrTableNumber }}</h3>
                        <button wire:click="closeQrModal" class="modal-close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="text-align:center;">
                        <img src="{{ url('/tables/' . $qrTableId . '/qr') }}" alt="QR Code for Table {{ $qrTableNumber }}" style="max-width: 320px; width: 100%; height: auto; margin-bottom: 1rem;" />
                        <div style="font-size: 1.2em; font-weight: bold; margin-top: 1rem;">Table {{ $qrTableNumber }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>