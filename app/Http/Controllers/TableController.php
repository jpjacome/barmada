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
     * Redirect to the order creation page using the unique token.
     */
    public function redirectToOrder($unique_token)
    {
        $table = Table::where('unique_token', $unique_token)->first();
        if (!$table || $table->status !== 'open') {
            if ($table && $table->editor) {
                app()->setLocale($table->editor->guestLocale());
            }
            return response()->view('orders.table-closed', ['table' => $table]);
        }
        // Speak the venue's language and currency on the guest menu.
        $editor = $table->editor;
        if ($editor) {
            app()->setLocale($editor->guestLocale());
        }
        $currency = $editor ? $editor->currencySymbol() : '$';
        // Only load products and tables for the correct editor
        $products = Product::where('editor_id', $table->editor_id)->orderBy('name')->get();
        $tables = Table::where('editor_id', $table->editor_id)->orderBy('table_number')->get();
        $selectedTableId = $table->id;
        $currentEditorId = $table->editor_id;
        // Render the order creation view with the table preselected and correct editor context
        return view('orders.create', compact('products', 'tables', 'selectedTableId', 'unique_token', 'currentEditorId', 'currency'));
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
            'note' => 'nullable|string|max:280',
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
            if (! $product->is_available) {
                // Sold out between page load and submit.
                abort(422, 'Product not available.');
            }
            $products[$productId] = $product;
        }

        // Create the order
        $order = \App\Models\Order::create([
            'table_id' => $table->id,
            'table_session_id' => $currentSession->id,
            'status' => 'pending',
            'note' => isset($validated['note']) ? strip_tags($validated['note']) : null,
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

        // Redirect to the confirmation page. The table token travels along
        // so the (stateless) confirmation can use the venue's language and
        // show the table number.
        return redirect()->route('orders.confirmation', ['t' => $unique_token]);
    }
}