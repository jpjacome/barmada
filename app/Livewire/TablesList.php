<?php

namespace App\Livewire;

use Livewire\Component;
use App\Actions\Orders\SettleOrder;
use App\Actions\Orders\ToggleItemPaid;
use App\Actions\Tables\ArchiveTable;
use App\Actions\Tables\CloseTable;
use App\Actions\Tables\OpenTable;
use App\Actions\Tables\RestoreTable;
use App\Actions\Tables\SaveClientInvoice;
use App\Actions\Tables\SettleTable;
use App\Exceptions\DomainActionException;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Livewire\Attributes\On;
use App\Support\TableBill;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class TablesList extends Component
{
    use AuthorizesRequests;

    public $tables = [];
    public $archivedTables = [];
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

    // Client invoice capture (per current session) [#6 done right]
    public $showInvoiceModal = false;
    public $invoiceTableId = null;
    public $invName = '';
    public $invTaxId = '';
    public $invAddress = '';
    public $invEmail = '';
    public $invPhone = '';

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
        $query = Table::whereNull('archived_at')->orderBy('table_number');
        $archivedQuery = Table::whereNotNull('archived_at')->orderBy('table_number');
        if ($user->is_admin) {
            // no extra scoping
        } else if ($user->is_editor) {
            $query->where('editor_id', $user->id);
            $archivedQuery->where('editor_id', $user->id);
        } else if ($user->is_staff) {
            $query->where('editor_id', $user->editor_id);
            $archivedQuery->where('editor_id', $user->editor_id);
        } else {
            $this->tables = collect();
            $this->archivedTables = collect();
            return;
        }
        $this->tables = $query->get();
        $this->archivedTables = $archivedQuery->get();
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Tables updated at ' . $this->lastUpdated;
    }

    /**
     * Retire a table that has order history [#5]: hidden from the grid and
     * the QR flow, kept in the database so reporting joins stay intact.
     * Only closed (fully settled) tables can be archived.
     */
    public function archiveTable($tableId)
    {
        $table = Table::findOrFail($tableId);
        $this->authorize('update', $table);

        try {
            app(ArchiveTable::class)->handle($table);
        } catch (DomainActionException $e) {
            $this->errorMessage = $e->getMessage();
            $this->showErrorModal = true;
            return;
        }

        $this->status = 'Table ' . ($table->table_number ?? $tableId) . ' archived.';
        $this->loadTables();
    }

    public function restoreTable($tableId)
    {
        $table = Table::findOrFail($tableId);
        $this->authorize('update', $table);
        app(RestoreTable::class)->handle($table);
        $this->status = 'Table ' . ($table->table_number ?? $tableId) . ' restored.';
        $this->loadTables();
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
        // Orders reference tables with a foreign key (no cascade), and the
        // sales history must survive for reporting — so tables with any
        // recorded orders cannot be deleted. Say so honestly. [F-5]
        $hasOrderHistory = Order::where('table_id', $tableId)->exists();
        if ($hasOrderHistory) {
            $this->errorMessage = 'Table ' . ($table->table_number ?? $tableId)
                . ' has recorded orders, so it cannot be deleted — its history is kept for reporting.'
                . ' Use Archive instead to retire it from service.';
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
        // One shared read model with the printed bill and the API session
        // endpoint, so every surface shows the same numbers.
        $table = $this->selectedTable ? Table::find($this->selectedTable) : null;

        if (! $table) {
            $this->tableOrders = [];
            $this->tableTotal = 0;
            $this->tablePaid = 0;
            $this->tableLeft = 0;

            return;
        }

        $bill = TableBill::build($table);
        $this->tableOrders = $bill['orders'];
        $this->tableTotal = $bill['total'];
        $this->tablePaid = $bill['paid'];
        $this->tableLeft = $bill['left'];
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

        $item = app(ToggleItemPaid::class)->handle($order, (int) $productId, (int) $itemIndex);

        if ($item) {
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
        app(SettleOrder::class)->handle($order);
        $this->refreshTableOrders();
    }

    public function toggleAllTableItems()
    {
        $table = Table::find($this->selectedTable);
        if (! $table) {
            return;
        }
        $this->authorize('update', $table);

        app(SettleTable::class)->handle($table);

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
        $orders = Order::countable()->where('table_id', $tableId)->get();
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

    /**
     * Capture (or edit) the client's tax-invoice details for the table's
     * CURRENT session — printed on the bill. The real version of the
     * decorative form removed during the trust-layer cleanup. [#6]
     */
    public function openInvoiceModal($tableId)
    {
        $table = Table::findOrFail($tableId);
        $this->authorize('update', $table);

        $session = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
        if (! $session) {
            $this->errorMessage = 'Open the table first — invoice details attach to the current session.';
            $this->showErrorModal = true;
            return;
        }

        $existing = \App\Models\ClientInvoice::where('table_session_id', $session->id)->first();
        $this->invName = $existing->name ?? '';
        $this->invTaxId = $existing->tax_id ?? '';
        $this->invAddress = $existing->address ?? '';
        $this->invEmail = $existing->email ?? '';
        $this->invPhone = $existing->phone ?? '';

        $this->invoiceTableId = $tableId;
        $this->showInvoiceModal = true;
    }

    public function saveInvoice()
    {
        $table = Table::findOrFail($this->invoiceTableId);
        $this->authorize('update', $table);

        $this->validate([
            'invName' => 'required|string|max:255',
            'invTaxId' => 'required|string|max:64',
            'invAddress' => 'nullable|string|max:255',
            'invEmail' => 'nullable|email|max:255',
            'invPhone' => 'nullable|string|max:32',
        ]);

        try {
            app(SaveClientInvoice::class)->handle($table, [
                'name' => $this->invName,
                'tax_id' => $this->invTaxId,
                'address' => $this->invAddress,
                'email' => $this->invEmail,
                'phone' => $this->invPhone,
            ]);
        } catch (DomainActionException) {
            // No current session (closed mid-edit): same silent dismiss as before.
            $this->closeInvoiceModal();

            return;
        }

        $this->status = 'Invoice details saved — they print on the bill.';
        $this->closeInvoiceModal();
    }

    public function closeInvoiceModal()
    {
        $this->showInvoiceModal = false;
        $this->invoiceTableId = null;
        $this->invName = $this->invTaxId = $this->invAddress = $this->invEmail = $this->invPhone = '';
        $this->resetValidation();
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

        try {
            if (in_array($table->status, ['closed', 'pending_approval'], true)) {
                // The OpenTable action defers to the Table model hook — the
                // single canonical session factory (exactly one session and
                // one rotated token per open).
                app(OpenTable::class)->handle($table);
            } elseif ($table->status === 'open') {
                app(CloseTable::class)->handle($table);
            }
        } catch (DomainActionException $e) {
            $this->errorMessage = $e->getMessage();
            $this->showErrorModal = true;
            return;
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

        // Mark all items as paid, then close (now guaranteed fully paid).
        app(SettleTable::class)->handle($guardTable);
        $this->refreshTableOrders();

        if ($guardTable->refresh()->status === 'open') {
            app(CloseTable::class)->handle($guardTable);
        }

        $this->loadTables(); // Refresh UI
    }
}