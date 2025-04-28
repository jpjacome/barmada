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
        
        // Handle both array and model instances
        if (is_array($order)) {
            $order = Order::with('items')->find($order['id']);
        }
        
        if ($order && $order->items) {
            foreach ($order->items as $item) {
                $products[$item->product_id] = $item->quantity;
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
                $this->orderDetails[$orderId] = [
                    'status' => $order['status'],
                    'table_id' => $order['table_id'],
                    'products' => $this->getOrderProducts($order)
                ];
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
            
            // Refresh pending orders
            $this->refreshPendingOrders();
            
            // Update the last updated timestamp
            $this->lastUpdated = now()->format('H:i:s');
        }
    }

    public function openStatusModal($orderId)
    {
        $order = Order::with('items.product')->findOrFail($orderId);
        $this->editingOrder = [
            'id' => $order->id,
            'status' => $order->status,
            'table_id' => $order->table_id,
            'products' => []
        ];

        // Group and sum quantities by product
        $groupedItems = [];
        foreach ($order->items as $item) {
            if (!isset($groupedItems[$item->product_id])) {
                $groupedItems[$item->product_id] = 0;
            }
            $groupedItems[$item->product_id] += $item->quantity;
        }

        // Set the summed quantities in the editingOrder
        foreach ($groupedItems as $productId => $quantity) {
            $this->editingOrder['products'][$productId] = $quantity;
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
        $order = Order::with('items')->find($this->editingOrder['id']);
        
        if ($order) {
            // Update table
            $order->table_id = $this->editingOrder['table_id'];
            
            // Delete all existing items
            $order->items()->delete();
            
            // Create new items with correct quantities
            $itemIndex = 0;
            foreach ($this->editingOrder['products'] as $productId => $quantity) {
                if ($quantity > 0) {
                    $product = Product::find($productId);
                    $order->items()->create([
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $product->price,
                        'is_paid' => false,
                        'item_index' => $itemIndex++
                    ]);
                }
            }
            
            $order->save();
            
            // Close the modal
            $this->closeModal();
            
            // Refresh the page
            $this->redirect(route('all-orders'));
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
        // First delete all order items
        \App\Models\OrderItem::query()->delete();
        
        // Then delete all orders
        Order::query()->delete();
        
        // Force refresh both lists
        $this->loadOrders();
        $this->loadPendingOrders();
        
        // Dispatch event to update UI
        $this->dispatch('orderDetailsUpdated', [
            'all' => 'removed'
        ]);
    }

    public function exportOrdersToXml()
    {
        // Create archive directory if it doesn't exist
        $archiveDir = storage_path('app/public/archive');
        if (!file_exists($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        
        // Generate filename with current date and time
        $filename = 'orders_' . now()->format('Y-m-d_H-i-s') . '.xml';
        $filepath = $archiveDir . '/' . $filename;
        
        // Fetch all orders with relationships
        $orders = Order::with(['table', 'items.product'])->get();
        
        // Create XML document
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><orders></orders>');
        
        foreach ($orders as $order) {
            $orderXml = $xml->addChild('order');
            $orderXml->addAttribute('id', $order->id);
            $orderXml->addChild('status', $order->status);
            $orderXml->addChild('created_at', $order->created_at);
            $orderXml->addChild('updated_at', $order->updated_at);
            
            // Add table information
            $tableXml = $orderXml->addChild('table');
            $tableXml->addAttribute('id', $order->table->id);
            
            // Add products
            $productsXml = $orderXml->addChild('products');
            foreach ($order->items as $item) {
                $productXml = $productsXml->addChild('product');
                $productXml->addAttribute('id', $item->product_id);
                $productXml->addChild('name', $item->product->name);
                $productXml->addChild('quantity', $item->quantity);
                $productXml->addChild('price', $item->price);
                $productXml->addChild('is_paid', $item->is_paid ? 'true' : 'false');
            }
        }
        
        // Save XML to file
        $xml->asXML($filepath);
        
        // Show success message with storage location
        $relativePath = 'storage/archive/' . $filename;
        session()->flash('message', "Orders exported to XML file: {$filename}. Stored in the archive folder.");
    }

    /**
     * Alias for exportOrdersToXml to match blade template usage
     */
    public function exportOrdersAsXml()
    {
        return $this->exportOrdersToXml();
    }

    public function acceptOrderRequest($orderId)
    {
        $order = \App\Models\Order::find($orderId);
        if ($order && $order->status === 'pending_approval') {
            $table = \App\Models\Table::find($order->table_id);
            if ($table) {
                // Set table status to open (triggers unique token generation)
                $table->status = 'open';
                $table->save();
                // Update order status to approved
                $order->status = 'approved';
                $order->save();
                // Optionally: notify or redirect the customer (could be via polling on the waiting page)
            }
            // Refresh lists
            $this->loadOrders();
            $this->loadPendingOrders();
            $this->dispatch('orderDetailsUpdated', [ $orderId => 'removed' ]);
        }
    }

    public function acceptTableRequest($tableId)
    {
        $table = \App\Models\Table::find($tableId);
        if ($table && $table->status === 'pending_approval') {
            $table->status = 'open';
            $table->save();
            // Optionally: notify the customer (they will be redirected by polling)
        }
        // Optionally: refresh the admin view
        $this->loadTables();
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