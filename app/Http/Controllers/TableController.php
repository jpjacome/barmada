<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class TableController extends Controller
{
    /**
     * Display a listing of the tables.
     */
    public function index()
    {
        $user = Auth::user();
        if ($user->is_admin) {
            $tables = Table::all();
        } else if ($user->is_editor) {
            $tables = Table::where('editor_id', $user->id)->get();
        } else if ($user->is_staff) {
            $tables = Table::where('editor_id', $user->editor_id)->get();
        } else {
            abort(403);
        }
        return view('tables.index', compact('tables'));
    }

    /**
     * Show the form for creating a new table.
     */
    public function create()
    {
        $user = Auth::user();
        if (!$user->is_admin && !$user->is_editor) {
            abort(403);
        }
        return view('tables.create');
    }

    /**
     * Store a newly created table in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->is_admin && !$user->is_editor) {
            abort(403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:tables,name',
            'capacity' => 'required|integer|min:1|max:20',
        ]);
        $data = [
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'is_occupied' => false,
        ];
        if ($user->is_admin) {
            $data['editor_id'] = $request->input('editor_id', null); // Admin can set or leave null
        } else {
            $data['editor_id'] = $user->id;
        }
        $table = Table::create($data);
        return redirect()->route('tables.index')->with('success', 'Table created successfully!');
    }

    /**
     * Display the specified table.
     */
    public function show(Table $table)
    {
        $this->authorize('view', $table);

        return view('tables.show', compact('table'));
    }

    /**
     * Show the form for editing the specified table.
     */
    public function edit(Table $table)
    {
        $this->authorize('update', $table);

        return view('tables.edit', compact('table'));
    }

    /**
     * Update the specified table in storage.
     */
    public function update(Request $request, Table $table)
    {
        $this->authorize('update', $table);
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:tables,name,' . $table->id,
            'capacity' => 'required|integer|min:1|max:20',
            'is_occupied' => 'boolean',
        ]);
        $table->update($validated);
        return redirect()->route('tables.index')->with('success', 'Table updated successfully!');
    }

    /**
     * Remove the specified table from storage.
     */
    public function destroy(Table $table)
    {
        $this->authorize('delete', $table);
        $table->delete();
        return redirect()->route('tables.index')->with('success', 'Table deleted successfully!');
    }

    /**
     * Redirect to the order creation page using the unique token.
     */
    public function redirectToOrder($unique_token)
    {
        $table = Table::where('unique_token', $unique_token)->first();
        if (!$table || $table->status !== 'open') {
            return response()->view('orders.table-closed', ['table' => $table]);
        }
        // Only load products and tables for the correct editor
        $products = Product::where('editor_id', $table->editor_id)->orderBy('name')->get();
        $tables = Table::where('editor_id', $table->editor_id)->orderBy('table_number')->get();
        $selectedTableId = $table->id;
        $currentEditorId = $table->editor_id;
        // Render the order creation view with the table preselected and correct editor context
        return view('orders.create', compact('products', 'tables', 'selectedTableId', 'unique_token', 'currentEditorId'));
    }

    /**
     * Generate a QR code image for the table's order link.
     */
    public function qrImage($tableId)
    {
        $table = \App\Models\Table::findOrFail($tableId);
        $this->authorize('view', $table);
        $editor = $table->editor;
        $orderLink = url('/qr-entry/' . rawurlencode($editor->username) . '/' . $table->table_number);
        $logoPath = public_path('images/logo-light.png');
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($orderLink)
            ->logoPath($logoPath)
            ->logoResizeToWidth(70)
            ->size(320)
            ->margin(10)
            ->build();
        return response($result->getString())
            ->header('Content-Type', $result->getMimeType());
    }

    /**
     * Store a guest order using the unique token.
     */
    public function storeGuestOrder(Request $request, $unique_token)
    {
        $table = Table::where('unique_token', $unique_token)->first();
        if (!$table || $table->status !== 'open') {
            // Stateless route: no session flash available, plain redirect.
            return redirect()->route('orders.waiting-approval');
        }

        $validated = $request->validate([
            'products' => 'required|array|min:1|max:50',
            'products.*' => 'integer|min:1|max:99',
        ]);

        // Find the current open TableSession for this table
        $currentSession = \App\Models\TableSession::where('table_id', $table->id)
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();

        if (!$currentSession) {
            // Stateless route: no session flash available, plain redirect.
            return redirect()->route('orders.waiting-approval');
        }

        // Resolve products up front; every product must belong to the
        // table's tenant. Rejecting before the order row exists avoids
        // persisting partial orders.
        $products = [];
        foreach (array_keys($validated['products']) as $productId) {
            $product = \App\Models\Product::forEditor($table->editor_id)->find($productId);
            if (! $product) {
                abort(422, 'Invalid product for this table.');
            }
            $products[$productId] = $product;
        }

        // Create the order
        $order = \App\Models\Order::create([
            'table_id' => $table->id,
            'table_session_id' => $currentSession->id,
            'status' => 'pending',
            'total_amount' => 0, // Calculate total based on products
            'editor_id' => $table->editor_id, // Assign the editor_id from the table
        ]);

        $totalAmount = 0;
        $itemIndex = 0; // Initialize item index
        foreach ($validated['products'] as $productId => $quantity) {
            $product = $products[$productId];
            for ($i = 0; $i < $quantity; $i++) {
                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => $product->price,
                    'item_index' => $itemIndex++, // Increment item index for each item
                ]);
                $totalAmount += $product->price;
            }
        }

        $order->update(['total_amount' => $totalAmount]);

        // Redirect to the confirmation page
        return redirect()->route('orders.confirmation')->with('success', 'Your order has been submitted!');
    }
}