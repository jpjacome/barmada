<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;

class AllOrdersList extends Component
{
    public $products = [];
    public $sort = 'created_at';
    public $direction = 'desc';
    public $sorting = false;
    public $showStatusModal = false;
    public $editingOrder = null;
    public $tables = [];
    public $pendingOrders = [];
    public $lastPendingOrdersHash = '';
    public $lastUpdated;
    public $orderDetails = [];

    public function mount()
    {
        $this->loadProducts();
        $this->loadTables();
        $this->loadOrders();
        $this->loadPendingOrders();
        $this->lastPendingOrdersHash = $this->calculatePendingOrdersHash();
        $this->lastUpdated = now()->format('H:i:s');
        $this->initializeOrderDetails();
    }

    public function loadProducts()
    {
        $this->products = Product::all()->keyBy('id')->toArray();
    }

    public function loadTables()
    {
        $this->tables = Table::all();
    }

    public function loadOrders()
    {
        // This method is now empty as the orders are loaded in the mount method
    }

    public function loadPendingOrders()
    {
        $this->pendingOrders = Order::where('status', 'pending')
            ->with('table')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    protected function initializeOrderDetails()
    {
        foreach ($this->pendingOrders as $order) {
            $this->orderDetails[$order['id']] = [
                'status' => $order['status'],
                'table_id' => $order['table_id'],
                'products' => $this->getOrderProducts($order)
            ];
        }
    }

    protected function getOrderProducts($order)
    {
        $products = [];
        for ($i = 1; $i <= 9; $i++) {
            $qty = $order["product{$i}_qty"] ?? 0;
            if ($qty > 0) {
                $products[$i] = $qty;
            }
        }
        return $products;
    }

    public function refreshPendingOrders()
    {
        $oldOrders = $this->pendingOrders;
        $this->loadPendingOrders();
        
        $changes = [];
        foreach ($this->pendingOrders as $order) {
            $orderId = $order['id'];
            $oldOrder = collect($oldOrders)->firstWhere('id', $orderId);
            
            if (!$oldOrder) {
                // New order
                $changes[$orderId] = 'new';
            } else {
                // Check for changes
                $oldDetails = $this->orderDetails[$orderId] ?? null;
                if ($oldDetails) {
                    $newDetails = [
                        'status' => $order['status'],
                        'table_id' => $order['table_id'],
                        'products' => $this->getOrderProducts($order)
                    ];
                    
                    if ($oldDetails !== $newDetails) {
                        $changes[$orderId] = 'updated';
                        $this->orderDetails[$orderId] = $newDetails;
                    }
                }
            }
        }
        
        // Check for removed orders
        foreach ($oldOrders as $oldOrder) {
            if (!collect($this->pendingOrders)->contains('id', $oldOrder['id'])) {
                $changes[$oldOrder['id']] = 'removed';
                unset($this->orderDetails[$oldOrder['id']]);
            }
        }
        
        if (!empty($changes)) {
            $this->lastUpdated = now()->format('H:i:s');
            $this->dispatch('orderDetailsUpdated', changes: $changes);
        }
    }

    protected function calculatePendingOrdersHash()
    {
        return md5(json_encode($this->pendingOrders));
    }

    public function sortBy($column)
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }
        
        $this->sorting = true;
    }

    public function toggleStatus($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->status = $order->status === 'pending' ? 'delivered' : 'pending';
            $order->save();
            
            // Force refresh of pending orders
            $this->pendingOrders = Order::where('status', 'pending')
                ->with('table')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
            
            // Update the last updated timestamp
            $this->lastUpdated = now()->format('H:i:s');
            
            // Dispatch event with changes
            $this->dispatch('orderDetailsUpdated', changes: [
                $orderId => $order->status === 'pending' ? 'updated' : 'removed'
            ]);
        }
    }

    public function openStatusModal($orderId)
    {
        $order = Order::findOrFail($orderId);
        $this->editingOrder = [
            'id' => $order->id,
            'status' => $order->status,
            'table_id' => $order->table_id,
            'products' => []
        ];

        // Load product quantities
        for ($i = 1; $i <= 9; $i++) {
            $this->editingOrder['products'][$i] = $order->{"product{$i}_qty"} ?? 0;
        }

        $this->showStatusModal = true;
    }

    public function toggleStatusInModal()
    {
        if ($this->editingOrder) {
            $order = Order::find($this->editingOrder['id']);
            if ($order) {
                $order->status = $order->status === 'pending' ? 'delivered' : 'pending';
                $order->save();
                
                // Refresh both lists
                $this->loadOrders();
                $this->loadPendingOrders();
                
                // Update the last updated timestamp
                $this->lastUpdated = now()->format('H:i:s');
                
                // Dispatch event with changes
                $this->dispatch('orderDetailsUpdated', changes: [
                    $order->id => $order->status === 'pending' ? 'updated' : 'removed'
                ]);
                
                $this->closeModal();
            }
        }
    }

    public function updateOrderTable($tableId)
    {
        if (!$this->editingOrder) return;
        $this->editingOrder['table_id'] = $tableId;
    }

    public function updateProductQuantity($productId, $quantity)
    {
        if (!$this->editingOrder) return;
        $this->editingOrder['products'][$productId] = max(0, intval($quantity));
    }

    public function incrementProductQuantity($productId)
    {
        if (!$this->editingOrder) return;
        $currentQuantity = $this->editingOrder['products'][$productId] ?? 0;
        $this->editingOrder['products'][$productId] = $currentQuantity + 1;
    }

    public function decrementProductQuantity($productId)
    {
        if (!$this->editingOrder) return;
        $currentQuantity = $this->editingOrder['products'][$productId] ?? 0;
        $this->editingOrder['products'][$productId] = max(0, $currentQuantity - 1);
    }

    public function saveChanges()
    {
        $order = Order::find($this->editingOrder['id']);
        
        if ($order) {
            // Update table
            $order->table_id = $this->editingOrder['table_id'];
            
            // Update product quantities
            foreach ($this->editingOrder['products'] as $productId => $quantity) {
                $order->{"product{$productId}_qty"} = $quantity;
            }
            
            $order->save();
            
            // Force refresh both lists
            $this->loadOrders();
            $this->loadPendingOrders();
            
            // Close the modal
            $this->showStatusModal = false;
            $this->editingOrder = null;
            
            // Dispatch event to update UI
            $this->dispatch('orderDetailsUpdated', [
                $order->id => 'updated'
            ]);
            
            // Refresh the entire page
            $this->redirect(request()->header('Referer'));
        }
    }

    public function deleteOrder($orderId)
    {
        $order = Order::find($orderId);
        
        if ($order) {
            $order->delete();
            
            // Force refresh both lists
            $this->loadOrders();
            $this->loadPendingOrders();
            
            // Dispatch event to update UI
            $this->dispatch('orderDetailsUpdated', [
                $orderId => 'removed'
            ]);
        }
    }

    public function deleteAllOrders()
    {
        // Delete all orders
        Order::truncate();
        
        // Force refresh both lists
        $this->loadOrders();
        $this->loadPendingOrders();
        
        // Dispatch event to update UI
        $this->dispatch('orderDetailsUpdated', [
            'all' => 'removed'
        ]);
    }

    public function closeModal()
    {
        $this->showStatusModal = false;
        $this->editingOrder = null;
    }

    public function render()
    {
        $query = Order::with('table');

        if ($this->sort === 'table_id') {
            $query->join('tables', 'orders.table_id', '=', 'tables.id')
                  ->select('orders.*')
                  ->orderBy('tables.id', $this->direction);
        } else {
            $query->orderBy($this->sort, $this->direction);
        }

        $allOrders = $query->get();

        $this->sorting = false;

        return view('livewire.all-orders-list', [
            'allOrders' => $allOrders,
            'products' => $this->products,
        ]);
    }
} 