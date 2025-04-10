<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

class OrdersList extends Component
{
    use WithPagination;

    public $pendingOrders = [];
    public $products = [];
    public $lastUpdated;
    public $refreshInterval = 5; // seconds
    public $sort = 'created_at';
    public $direction = 'desc';
    public $sorting = false;
    public $editingOrder = null;
    public $tables = [];
    public $showEditModal = false;

    public function mount()
    {
        $this->loadOrders();
        $this->tables = Table::all();
    }

    public function loadOrders()
    {
        // Load pending orders
        $this->pendingOrders = Order::where('status', 'pending')
            ->with('table')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // Load products for display
        $this->products = Product::all()->keyBy('id')->toArray();

        $this->lastUpdated = now()->format('Y-m-d H:i:s');
    }

    public function updateOrderStatus($orderId, $status)
    {
        $order = Order::findOrFail($orderId);
        $order->status = $status;
        $order->save();

        // Update the order in pendingOrders array
        $this->pendingOrders = array_filter($this->pendingOrders, function($order) use ($orderId) {
            return $order['id'] != $orderId;
        });

        // Update the lastUpdated timestamp
        $this->lastUpdated = now()->format('Y-m-d H:i:s');
    }

    public function refreshOrders()
    {
        // Get current pending orders
        $currentPendingIds = collect($this->pendingOrders)->pluck('id')->toArray();
        
        // Load new pending orders
        $newPendingOrders = Order::where('status', 'pending')
            ->with('table')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
            
        // Get new pending order IDs
        $newPendingIds = collect($newPendingOrders)->pluck('id')->toArray();
        
        // Only update if there are actual changes
        if ($currentPendingIds != $newPendingIds) {
            $this->pendingOrders = $newPendingOrders;
            $this->lastUpdated = now()->format('Y-m-d H:i:s');
        }
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

    public function editOrder($orderId)
    {
        $order = Order::with('table')->findOrFail($orderId);
        
        // Initialize editingOrder with current values
        $this->editingOrder = [
            'id' => $order->id,
            'table_id' => $order->table_id,
            'products' => []
        ];

        // Load existing product quantities
        for ($i = 1; $i <= 9; $i++) {
            $qty = $order->{"product{$i}_qty"} ?? 0;
            $this->editingOrder['products'][$i] = $qty;
        }

        $this->showEditModal = true;
    }

    public function updateOrder()
    {
        if (!$this->editingOrder || !$this->editingOrder['id']) {
            return;
        }

        $order = Order::findOrFail($this->editingOrder['id']);
        
        // Update table
        $order->table_id = $this->editingOrder['table_id'];
        
        // Update product quantities
        for ($i = 1; $i <= 9; $i++) {
            $quantity = $this->editingOrder['products'][$i] ?? 0;
            $order->{"product{$i}_qty"} = $quantity;
        }
        
        $order->save();
        
        // Refresh the orders list
        $this->loadOrders();
        
        // Close the modal
        $this->showEditModal = false;
        $this->editingOrder = null;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingOrder = null;
    }

    public function render()
    {
        $query = Order::with('table');

        // Apply sorting
        if ($this->sort === 'table_id') {
            $query->join('tables', 'orders.table_id', '=', 'tables.id')
                  ->select('orders.*')
                  ->orderBy('tables.id', $this->direction);
        } else {
            $query->orderBy($this->sort, $this->direction);
        }

        $allOrders = $query->get();

        // Reset sorting state after render
        $this->sorting = false;

        return view('livewire.orders-list', [
            'pendingOrders' => $this->pendingOrders,
            'allOrders' => $allOrders,
            'products' => $this->products,
            'lastUpdated' => $this->lastUpdated,
        ]);
    }
} 