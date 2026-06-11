<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Table;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Livewire\Attributes\On;
use App\Models\ActivityLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class TablesList extends Component
{
    use AuthorizesRequests;

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

    public $showClientInfoModal = false;
    public $clientInfoTableId = null;

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
        } else if ($user->is_staff) {
            $this->tables = Table::where('editor_id', $user->editor_id)->orderBy('table_number')->get();
        } else {
            $this->tables = collect();
        }
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Tables updated at ' . $this->lastUpdated;
    }

    private function loadProducts()
    {
        $rawProducts = Product::all();

        $this->products = $rawProducts->mapWithKeys(function ($product) {
            return [$product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'icon_value' => $product->icon_type === 'svg' ? $product->icon_value : $product->icon,
                'icon_type' => $product->icon_type
            ]];
        })->toArray();
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
        $this->authorize('create', Table::class);
        // Admins create under their own id; editors and staff under their tenant.
        $editorId = $user->is_admin ? $user->id : $user->effectiveEditorId();
        $existingNumbers = Table::where('editor_id', $editorId)->pluck('table_number')->toArray();
        $nextTableNumber = 1;
        while (in_array($nextTableNumber, $existingNumbers)) {
            $nextTableNumber++;
        }
        $data = [
            'orders' => 0,
            'editor_id' => $editorId,
            'table_number' => $nextTableNumber,
        ];
        Table::create($data);
        $this->toggleAddForm();
        $this->status = 'Table added successfully!';
        $this->dispatch('refresh-tables');
    }

    public function deleteTable($tableId)
    {
        $table = Table::findOrFail($tableId);
        $this->authorize('delete', $table);
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
        $table = Table::findOrFail($tableId);
        $this->authorize('view', $table);

        $this->selectedTable = $table->id;
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
        // Resolve the order through EditorScope first; order items carry
        // no tenant key of their own.
        $order = Order::find($orderId);
        if (! $order) {
            return;
        }
        $this->authorize('update', $order);

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

            // Check if all items in the order are paid, and update order status
            $order = Order::with('items')->find($orderId);
            if ($order && $order->items->every(function ($item) { return $item->is_paid; })) {
                $order->status = 'delivered';
                $order->save();
            }
            // Do not revert to pending if not all items are paid

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
        $this->authorize('update', $order);
        $items = $order->items;
        // Always mark all items as paid
        $totalAmount = 0;
        $items->each(function ($item) use (&$totalAmount) {
            $item->is_paid = true;
            $item->save();
            $totalAmount += $item->price;
        });
        // Update order's status and payment fields
        $order->amount_paid = $items->sum('price');
        $order->amount_left = 0;
        if ($items->count() > 0) {
            $order->status = 'delivered';
        }
        $order->save();
        // Log the bulk payment activity
        $table = Table::find($this->selectedTable);
        $editorId = $table ? $table->editor_id : null;
        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $this->selectedTable,
            'order_id' => $orderId,
            'amount' => $totalAmount,
            'description' => "All items in Order #{$orderId} marked as paid for Table #{$this->selectedTable}",
            'metadata' => [
                'items_count' => $items->count(),
                'action' => 'paid'
            ],
            'editor_id' => $editorId,
        ]);
        $this->refreshTableOrders();
    }

    public function toggleAllTableItems()
    {
        $table = Table::find($this->selectedTable);
        if (! $table) {
            return;
        }
        $this->authorize('update', $table);

        $orders = Order::where('table_id', $table->id)->get();
        $allItems = OrderItem::whereIn('order_id', $orders->pluck('id'))->get();
        // Always mark all items as paid
        $totalAmount = 0;
        $allItems->each(function ($item) use (&$totalAmount) {
            $item->is_paid = true;
            $item->save();
            $totalAmount += $item->price;
        });
        // After marking as paid, update each order's status and payment fields
        foreach ($orders as $order) {
            $items = $order->items;
            $order->amount_paid = $items->sum('price');
            $order->amount_left = 0;
            if ($items->count() > 0) {
                $order->status = 'delivered';
            }
            $order->save();
        }
        // Log the table-wide payment activity
        $table = Table::find($this->selectedTable);
        $editorId = $table ? $table->editor_id : null;
        ActivityLog::create([
            'type' => 'payment',
            'table_id' => $this->selectedTable,
            'amount' => $totalAmount,
            'description' => "All items for Table #{$this->selectedTable} marked as paid",
            'metadata' => [
                'orders_count' => $orders->count(),
                'items_count' => $allItems->count(),
                'action' => 'paid'
            ],
            'editor_id' => $editorId,
        ]);
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
        $table = Table::findOrFail($tableId);
        $this->authorize('update', $table);
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

    public function openClientInfoModal($tableId)
    {
        $this->showClientInfoModal = true;
        $this->clientInfoTableId = $tableId;
    }

    public function closeClientInfoModal()
    {
        $this->showClientInfoModal = false;
        $this->clientInfoTableId = null;
    }

    public function render()
    {
        $user = Auth::user();
        $editorName = $user->username; // Use username for QR links
        $tenant = $user->is_admin ? $user : \App\Models\User::find($user->effectiveEditorId());
        return view('livewire.tables-list', [
            'editorName' => $editorName,
            'currency' => $tenant ? $tenant->currencySymbol() : '$',
        ]);
    }

    protected function updateTableStatus($tableId)
    {
        // Payment completion no longer auto-closes the table [F-11]: closing
        // a session (which kills the QR token and ejects seated guests who
        // pay per round) must always be an explicit staff action via
        // toggleTableStatus() or payAndCloseTable().
        $this->loadTables(); // Ensure UI and state are refreshed
    }

    public function toggleTableStatus($tableId)
    {
        $table = Table::find($tableId);
        if (!$table) return;
        $this->authorize('update', $table);

        $user = Auth::user();
        $today = now()->toDateString();

        if ($table->status === 'closed' || $table->status === 'pending_approval') {
            // Always create a new session with a new unique token
            $maxSessionNumber = $table->sessions()->where('date', $today)->max('session_number');
            $sessionNumber = $maxSessionNumber ? $maxSessionNumber + 1 : 1;
            $newToken = (string) \Illuminate\Support\Str::uuid();
            $table->unique_token = $newToken;
            $table->status = 'open';
            $table->save();
            \App\Models\TableSession::create([
                'table_id' => $table->id,
                'session_number' => $sessionNumber,
                'date' => $today,
                'unique_token' => $newToken,
                'status' => 'open',
                'opened_at' => now(),
                'opened_by' => $user->id,
                'editor_id' => $table->editor_id,
            ]);
        } elseif ($table->status === 'open') {
            // Prevent closing if there are unpaid items
            if (!$this->isTableFullyPaid($tableId)) {
                $this->errorMessage = 'Cannot close table until all items are paid.';
                $this->showErrorModal = true;
                return;
            }
            // Close the table and the current open session
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

    public function payAndCloseTable()
    {
        $guardTable = Table::find($this->selectedTable);
        if (! $guardTable) {
            return;
        }
        $this->authorize('update', $guardTable);

        // Mark all items as paid
        $this->toggleAllTableItems();
        // Directly close the table after marking all items as paid
        $table = Table::find($this->selectedTable);
        if ($table && $table->status === 'open') {
            $table->status = 'closed';
            $table->save();
        }
        $this->loadTables(); // Refresh UI
    }
}