<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Table;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Livewire\Attributes\On;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

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

    public $showQrModal = false;
    public $qrTableId = null;
    public $qrTableNumber = null;

    protected $listeners = [
        'openQrModal',
        'closeQrModal',
    ];

    public function mount()
    {
        $this->loadTables();
        $this->loadProducts();
    }

    public function loadTables()
    {
        $user = Auth::user();
        if ($user->is_admin) {
            $this->tables = Table::orderBy('table_number')->get();
        } else if ($user->is_editor) {
            $this->tables = Table::where('editor_id', $user->id)->orderBy('table_number')->get();
        } else {
            $this->tables = collect();
        }
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
        $user = Auth::user();
        if (!$user->is_admin && !$user->is_editor) {
            abort(403);
        }
        // Find the lowest available table_number for this editor
        $existingNumbers = Table::where('editor_id', $user->id)->pluck('table_number')->toArray();
        $nextTableNumber = 1;
        while (in_array($nextTableNumber, $existingNumbers)) {
            $nextTableNumber++;
        }
        $data = [
            'orders' => 0,
            'editor_id' => $user->id,
            'table_number' => $nextTableNumber,
        ];
        Table::create($data);
        $this->toggleAddForm();
        $this->status = 'Table added successfully!';
        $this->dispatch('refresh-tables');
    }

    public function deleteTable($tableId)
    {
        $user = Auth::user();
        $table = Table::findOrFail($tableId);
        if (!$user->is_admin && !($user->is_editor && $table->editor_id == $user->id)) {
            abort(403);
        }
        // Check if the table has any active orders
        $hasActiveOrders = Order::where('table_id', $tableId)->exists();
        if ($hasActiveOrders) {
            $this->errorMessage = 'Cannot delete table #' . $tableId . ' because it has active orders.';
            $this->showErrorModal = true;
            return;
        }
        // Delete the table if it has no active orders
        $table->delete();
        $this->status = 'Table #' . $table->table_number . ' deleted successfully!';
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
        // Find the current open TableSession for this table
        $currentSession = null;
        if ($this->selectedTable) {
            $currentSession = \App\Models\TableSession::where('table_id', $this->selectedTable)
                ->whereIn('status', ['open', 'reopened'])
                ->latest('opened_at')
                ->first();
        }
        if ($currentSession) {
            $orders = Order::where('table_id', $this->selectedTable)
                ->where('table_session_id', $currentSession->id)
                ->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $orders = collect();
        }
        $this->tableOrders = $orders->map(function ($order) {
            $totalAmount = $order->items->sum(function ($item) {
                return $item->price;
            });
            $paidAmount = $order->items->where('is_paid', true)->sum(function ($item) {
                return $item->price;
            });
            $leftAmount = $totalAmount - $paidAmount;
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

            // Get the table's editor_id
            $table = Table::find($this->selectedTable);
            $editorId = $table ? $table->editor_id : null;

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
                ],
                'editor_id' => $editorId,
            ]);
            $this->updateTableStatus($this->selectedTable);
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
        $table = Table::find($this->selectedTable);
        $editorId = $table ? $table->editor_id : null;
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
            ],
            'editor_id' => $editorId,
        ]);
        
        // Refresh the order data
        $this->updateTableStatus($this->selectedTable);
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
        $table = Table::find($this->selectedTable);
        $editorId = $table ? $table->editor_id : null;
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
            ],
            'editor_id' => $editorId,
        ]);
        
        // Refresh the order data
        $this->updateTableStatus($this->selectedTable);
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

    public function isTableFullyPaid($tableId)
    {
        $orders = Order::where('table_id', $tableId)->get();
        $totalLeft = $orders->sum(function ($order) {
            return $order->items->sum(function ($item) {
                return $item->is_paid ? 0 : $item->price;
            });
        });

        return $totalLeft === 0;
    }

    public function openQrModal($tableId, $tableNumber)
    {
        $this->showQrModal = true;
        $this->qrTableId = $tableId;
        $this->qrTableNumber = $tableNumber;
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->qrTableId = null;
        $this->qrTableNumber = null;
    }

    public function render()
    {
        $user = Auth::user();
        $editorName = $user->name; // Or use a slug if available/preferred
        return view('livewire.tables-list', [
            'editorName' => $editorName
        ]);
    }

    protected function updateTableStatus($tableId)
    {
        $table = Table::find($tableId);
        if (!$table) return;

        $orders = Order::where('table_id', $tableId)->get();
        $totalLeft = $orders->sum(function ($order) {
            return $order->items->sum(function ($item) {
                return $item->is_paid ? 0 : $item->price;
            });
        });

        $table->status = $totalLeft === 0 ? 'closed' : 'open';
        $table->save();
    }

    public function toggleTableStatus($tableId)
    {
        $table = Table::find($tableId);
        if (!$table) return;

        $user = Auth::user();
        $today = now()->toDateString();

        // Only allow opening if not already open for today
        if ($table->status === 'closed' || $table->status === 'pending_approval') {
            // Check for existing open session for today
            $existingSession = $table->sessions()
                ->where('date', $today)
                ->whereIn('status', ['open', 'reopened'])
                ->first();
            if ($existingSession) {
                // Prevent duplicate open sessions
                $table->status = 'open';
                $table->unique_token = $existingSession->unique_token;
                $table->save();
                $this->loadTables();
                return;
            }
            // Get next session number for today
            $maxSessionNumber = $table->sessions()->where('date', $today)->max('session_number');
            $sessionNumber = $maxSessionNumber ? $maxSessionNumber + 1 : 1;
            $uniqueToken = (string) \Illuminate\Support\Str::uuid();
            // Create new TableSession
            $session = \App\Models\TableSession::create([
                'table_id' => $table->id,
                'session_number' => $sessionNumber,
                'date' => $today,
                'unique_token' => $uniqueToken,
                'status' => 'open',
                'opened_at' => now(),
                'opened_by' => $user->id,
                'editor_id' => $table->editor_id,
            ]);
            $table->status = 'open';
            $table->unique_token = $uniqueToken;
            $table->save();
        } elseif ($table->status === 'open') {
            // Close the table and session
            $openSession = $table->sessions()
                ->whereIn('status', ['open', 'reopened'])
                ->latest('opened_at')
                ->first();
            if ($openSession) {
                $openSession->status = 'closed';
                $openSession->closed_at = now();
                $openSession->closed_by = $user->id;
                $openSession->save();
            }
            $table->status = 'closed';
            $table->unique_token = null;
            $table->save();
        }
        $this->loadTables();
    }
}