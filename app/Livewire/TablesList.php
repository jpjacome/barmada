<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Table;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Livewire\Attributes\On;
use App\Models\ActivityLog;

class TablesList extends Component
{
    public $tables = [];
    public $lastUpdated;
    public $status = 'Loading tables...';
    public $refreshInterval = 10; // in seconds
    
    // Form properties
    public $showAddForm = false;
    
    // Modal properties
    public $showOrdersModal = false;
    public $selectedTable = null;
    public $tableOrders = [];
    public $products = [];
    public $selectedItems = [];
    public $tableTotal = 0;
    public $tablePaid = 0;
    public $tableLeft = 0;
    public $showErrorModal = false;
    public $errorMessage = '';

    public $editingReference = null;
    public $referenceText = '';

    public $visibleOrderProducts = [];

    public function mount()
    {
        $this->loadTables();
        $this->loadProducts();
    }

    public function loadTables()
    {
        $this->tables = Table::orderBy('id')->get();
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Tables updated at ' . $this->lastUpdated;
    }

    private function loadProducts()
    {
        $rawProducts = Product::all();
        \Log::info('Raw products from database: ' . json_encode($rawProducts));
        
        $this->products = $rawProducts->mapWithKeys(function ($product) {
            \Log::info("Processing product {$product->id}: icon={$product->icon}, type={$product->icon_type}");
            
            return [$product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'icon_value' => $product->icon_type === 'svg' ? $product->icon_value : $product->icon,
                'icon_type' => $product->icon_type
            ]];
        })->toArray();
        
        \Log::info('Processed products array: ' . json_encode($this->products));
    }

    #[On('refresh-tables')]
    public function refreshTables()
    {
        $this->loadTables();
    }

    public function toggleAddForm()
    {
        $this->showAddForm = !$this->showAddForm;
        $this->resetForm();
    }

    public function resetForm()
    {
        // No form fields to reset
    }

    public function addTable()
    {
        // Find the lowest available ID
        $existingIds = Table::orderBy('id')->pluck('id')->toArray();
        $newId = 1;
        
        // Find the first gap in the sequence
        foreach ($existingIds as $index => $id) {
            if ($id != $index + 1) {
                $newId = $index + 1;
                break;
            }
            $newId = count($existingIds) + 1;
        }
        
        // Create the table with the specific ID
        Table::create([
            'id' => $newId,
            'orders' => 0,
        ]);

        $this->toggleAddForm();
        $this->status = 'Table added successfully!';
        $this->dispatch('refresh-tables');
    }

    public function deleteTable($tableId)
    {
        $table = Table::findOrFail($tableId);
        
        // Check if the table has any active orders
        $hasActiveOrders = Order::where('table_id', $tableId)->exists();
        
        if ($hasActiveOrders) {
            $this->errorMessage = 'Cannot delete table #' . $tableId . ' because it has active orders.';
            $this->showErrorModal = true;
            return;
        }
        
        // Delete the table if it has no active orders
        $table->delete();
        $this->status = 'Table #' . $tableId . ' deleted successfully!';
        $this->dispatch('refresh-tables');
    }

    public function viewTableOrders($tableId)
    {
        $this->selectedTable = $tableId;
        $this->loadTableOrders();
        $this->showOrdersModal = true;
        
        // Debug log
        \Log::info("Modal should be shown: tableId={$tableId}, showOrdersModal={$this->showOrdersModal}");
    }
    
    public function loadTableOrders()
    {
        $orders = Order::where('table_id', $this->selectedTable)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->tableOrders = $orders->map(function ($order) {
            $totalAmount = $order->items->sum(function ($item) {
                return $item->price;
            });
            $paidAmount = $order->items->where('is_paid', true)->sum(function ($item) {
                return $item->price;
            });
            $leftAmount = $totalAmount - $paidAmount;

            // Keep items ungrouped
            $ungroupedItems = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_index' => $item->item_index,
                    'price' => $item->price,
                    'is_paid' => $item->is_paid,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'icon_type' => $item->product->icon_type,
                        'icon_value' => $item->product->icon_value,
                    ]
                ];
            })->toArray();

            return [
                'id' => $order->id,
                'created_at' => $order->created_at,
                'total_amount' => $totalAmount,
                'amount_paid' => $paidAmount,
                'amount_left' => $leftAmount,
                'items' => $ungroupedItems
            ];
        })->toArray();

        // Calculate table totals
        $this->tableTotal = collect($this->tableOrders)->sum('total_amount');
        $this->tablePaid = collect($this->tableOrders)->sum('amount_paid');
        $this->tableLeft = $this->tableTotal - $this->tablePaid;
    }
    
    public function closeOrdersModal()
    {
        $this->showOrdersModal = false;
        $this->selectedTable = null;
        $this->tableOrders = [];
        
        // Debug log
        \Log::info("Modal was closed, showOrdersModal={$this->showOrdersModal}");
    }

    public function refreshModal()
    {
        // This method keeps the modal data fresh while it's open
        if ($this->showOrdersModal && $this->selectedTable) {
            \Log::info("Refreshing modal for table {$this->selectedTable}");
            $this->loadTableOrders();
        }
    }

    public function selectItem($orderId, $productId, $itemIndex)
    {
        $orderItem = OrderItem::where('order_id', $orderId)
            ->where('product_id', $productId)
            ->where('item_index', $itemIndex)
            ->first();

        if ($orderItem) {
            // Toggle the paid status
            $orderItem->is_paid = !$orderItem->is_paid;
            $orderItem->save();

            // Log the payment activity
            ActivityLog::create([
                'type' => 'payment',
                'table_id' => $this->selectedTable,
                'order_id' => $orderId,
                'amount' => $orderItem->price,
                'description' => $orderItem->is_paid 
                    ? "Item #{$itemIndex} of Product #{$productId} marked as paid in Order #{$orderId} for Table #{$this->selectedTable}"
                    : "Item #{$itemIndex} of Product #{$productId} marked as unpaid in Order #{$orderId} for Table #{$this->selectedTable}",
                'metadata' => [
                    'product_id' => $productId,
                    'item_index' => $itemIndex,
                    'action' => $orderItem->is_paid ? 'paid' : 'unpaid'
                ]
            ]);

            $this->refreshTableOrders();
        }
    }

    public function isItemSelected($orderId, $productId, $itemIndex)
    {
        $orderItem = OrderItem::where('order_id', $orderId)
            ->where('product_id', $productId)
            ->where('item_index', $itemIndex)
            ->first();

        return $orderItem && $orderItem->is_paid;
    }

    public function refreshTableOrders()
    {
        if ($this->selectedTable) {
            $this->loadTableOrders();
        }
    }

    public function toggleAllItems($orderId)
    {
        $order = Order::findOrFail($orderId);
        $items = $order->items;
        
        // Check if all items are currently paid
        $allPaid = $items->every(function ($item) {
            return $item->is_paid;
        });
        
        // Toggle all items to the opposite state
        $totalAmount = 0;
        $items->each(function ($item) use ($allPaid, &$totalAmount) {
            $item->is_paid = !$allPaid;
            $item->save();
            $totalAmount += $item->price;
        });
        
        // Log the bulk payment activity
        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $this->selectedTable,
            'order_id' => $orderId,
            'amount' => $totalAmount,
            'description' => !$allPaid 
                ? "All items in Order #{$orderId} marked as paid for Table #{$this->selectedTable}"
                : "All items in Order #{$orderId} marked as unpaid for Table #{$this->selectedTable}",
            'metadata' => [
                'items_count' => $items->count(),
                'action' => !$allPaid ? 'paid' : 'unpaid'
            ]
        ]);
        
        // Refresh the order data
        $this->refreshTableOrders();
    }

    public function toggleAllTableItems()
    {
        $orders = Order::where('table_id', $this->selectedTable)->get();
        $allItems = OrderItem::whereIn('order_id', $orders->pluck('id'))->get();
        
        // Check if all items are currently paid
        $allPaid = $allItems->every(function ($item) {
            return $item->is_paid;
        });
        
        // Toggle all items to the opposite state
        $totalAmount = 0;
        $allItems->each(function ($item) use ($allPaid, &$totalAmount) {
            $item->is_paid = !$allPaid;
            $item->save();
            $totalAmount += $item->price;
        });
        
        // Log the table-wide payment activity
        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $this->selectedTable,
            'amount' => $totalAmount,
            'description' => !$allPaid 
                ? "All items for Table #{$this->selectedTable} marked as paid"
                : "All items for Table #{$this->selectedTable} marked as unpaid",
            'metadata' => [
                'orders_count' => $orders->count(),
                'items_count' => $allItems->count(),
                'action' => !$allPaid ? 'paid' : 'unpaid'
            ]
        ]);
        
        // Refresh the order data
        $this->refreshTableOrders();
    }

    public function startEditingReference($tableId)
    {
        $this->editingReference = $tableId;
        $this->referenceText = Table::find($tableId)->reference ?? '';
    }

    public function saveReference($tableId)
    {
        $table = Table::find($tableId);
        $table->reference = $this->referenceText;
        $table->save();
        
        $this->editingReference = null;
        $this->referenceText = '';
    }

    public function cancelEditingReference()
    {
        $this->editingReference = null;
        $this->referenceText = '';
    }

    public function closeErrorModal()
    {
        $this->showErrorModal = false;
        $this->errorMessage = '';
    }

    public function toggleOrderProducts($orderId)
    {
        if (isset($this->visibleOrderProducts[$orderId])) {
            unset($this->visibleOrderProducts[$orderId]);
        } else {
            $this->visibleOrderProducts[$orderId] = true;
        }
    }

    public function isOrderProductsVisible($orderId)
    {
        return isset($this->visibleOrderProducts[$orderId]);
    }

    public function render()
    {
        return view('livewire.tables-list');
    }
} 