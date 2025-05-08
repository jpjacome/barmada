<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSessionRequest;
use App\Models\TableSession;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AllOrdersList extends Component
{
    use WithPagination;

    public $products = [];
    public $sort = 'created_at';
    public $direction = 'desc';
    public $sorting = false;
    public $showStatusModal = false;
    public $editingOrder = null;
    public $tables = [];
    public $activeTables = [];
    public $pendingOrders = [];
    public $lastPendingOrdersHash = '';
    public $lastUpdated;
    public $orderDetails = [];
    public $perPage = 15;

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
        $user = Auth::user();
        // Only show tables for the current editor (or all if admin)
        if ($user->is_admin) {
            $this->tables = Table::whereIn('status', ['pending_approval', 'open'])->get();
        } else if ($user->is_editor) {
            $this->tables = Table::where('editor_id', $user->id)
                ->whereIn('status', ['pending_approval', 'open'])
                ->get();
        } else if ($user->is_staff) {
            $this->tables = Table::where('editor_id', $user->editor_id)
                ->whereIn('status', ['pending_approval', 'open'])
                ->get();
        } else {
            $this->tables = collect();
        }
        $this->activeTables = $this->tables->map(function ($table) {
            // For open tables, get the current session
            $currentSession = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
            $approvedClients = 0;
            $pendingClients = collect();
            if ($table->status === 'open' && $currentSession) {
                $approvedClients = $currentSession->sessionRequests()->where('status', 'approved')->count();
                $pendingClients = $currentSession->sessionRequests()
                    ->where('status', 'pending')
                    ->get();
            } else {
                // For pending_approval, get requests not linked to a session
                $pendingClients = TableSessionRequest::whereNull('table_session_id')
                    ->where('status', 'pending')
                    ->whereDate('created_at', now()->toDateString())
                    ->get();
            }
            return [
                'id' => $table->id,
                'table_number' => $table->table_number,
                'status' => $table->status,
                'approved_clients' => $approvedClients,
                'pending_clients' => $pendingClients,
            ];
        });
    }

    public function loadOrders()
    {
        // This method is now empty as the orders are loaded in the mount method
    }

    public function loadPendingOrders()
    {
        $user = Auth::user();
        $query = Order::where('status', 'pending')->with(['table', 'items.product']);
        if (!$user->is_admin) {
            $query->where('editor_id', $user->id);
        }
        $this->pendingOrders = $query->orderBy('created_at', 'desc')->limit(100)->get()->toArray();
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
        $user = Auth::user();
        $editorId = $user->is_admin ? null : $user->id;
        if (!$editorId) {
            // Optionally, block admin from exporting or export all data to a separate admin folder
            session()->flash('message', 'Only editors can export their own orders.');
            return;
        }
        // Create per-editor archive directory if it doesn't exist
        $archiveDir = storage_path('app/public/archive/' . $editorId);
        if (!file_exists($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        // Generate filename with current date and time, including editor_id
        $filename = 'orders_' . $editorId . '_' . now()->format('Y-m-d_H-i-s') . '.xml';
        $filepath = $archiveDir . '/' . $filename;
        // Fetch all orders for this editor with relationships
        $orders = Order::with(['table', 'items.product'])->where('editor_id', $editorId)->get();
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
        $relativePath = 'storage/archive/' . $editorId . '/' . $filename;
        session()->flash('message', "Orders exported to XML file: {$filename}. Stored in your archive folder.");
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

    public function approveTableAndFirstClient($tableId)
    {
        $table = Table::find($tableId);
        if ($table && $table->status === 'pending_approval') {
            // Open the table
            $table->status = 'open';
            $table->save();

            // Approve the first pending TableSessionRequest for this table
            $pendingRequest = TableSessionRequest::whereHas('tableSession', function ($query) use ($table) {
                $query->where('table_id', $table->id);
            })
            ->whereNull('table_session_id')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->first();

            if ($pendingRequest) {
                $pendingRequest->status = 'approved';
                $pendingRequest->approved_at = now();
                $pendingRequest->save();
            }
        }
        $this->loadTables();
    }

    public function approveClientRequest($requestId)
    {
        $request = TableSessionRequest::find($requestId);
        if ($request && $request->status === 'pending' && $request->table_session_id) {
            $request->status = 'approved';
            $request->approved_at = now();
            $request->save();
        }
        $this->loadTables();
    }

    public function closeModal()
    {
        $this->showStatusModal = false;
        $this->editingOrder = null;
    }

    public function render()
    {
        $user = Auth::user();
        $query = Order::with(['table', 'items.product']);

        if (!$user->is_admin) {
            $query->where('editor_id', $user->id);
        }

        // Only show orders from the last 2 days
        $query->where('created_at', '>=', Carbon::now()->subDays(1)->startOfDay());

        if ($this->sort === 'table_id') {
            $query->join('tables', 'orders.table_id', '=', 'tables.id')
                  ->select('orders.*')
                  ->orderBy('tables.id', $this->direction);
        } else {
            $query->orderBy($this->sort, $this->direction);
        }

        $allOrders = $query->paginate($this->perPage);

        $this->sorting = false;

        return view('livewire.all-orders-list', [
            'allOrders' => $allOrders,
            'products' => $this->products,
        ]);
    }
}