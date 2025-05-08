<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $sort = $request->query('sort');
        $direction = $request->query('direction', 'asc');
        
        // Get all pending orders for the latest orders panel
        $pendingOrders = Order::with('table')
            ->where('status', 'pending')
            ->when($user->is_editor, fn($q) => $q->where('editor_id', $user->id))
            ->latest()->get();
        
        // Start with a base query for all orders
        $query = Order::with('table');
        
        if ($user->is_editor) {
            $query->where('editor_id', $user->id);
        }
        
        // Apply sorting
        if ($sort === 'table') {
            $query->join('tables', 'orders.table_id', '=', 'tables.id')
                  ->select('orders.*')
                  ->orderBy('tables.id', $direction);
        } elseif ($sort === 'status') {
            $query->orderBy('status', $direction);
        } elseif ($sort === 'id') {
            $query->orderBy('id', $direction);
        } elseif ($sort === 'created_at') {
            $query->orderBy('created_at', $direction);
        } else {
            // Default sort by latest
            $query->latest();
        }
        
        $orders = $query->get();
        $products = Product::all()->keyBy('id');
        
        return view('orders.index', compact('orders', 'products', 'sort', 'direction', 'pendingOrders'));
    }

    /**
     * Show the form for creating a new order.
     */
    public function create()
    {
        $user = Auth::user();
        if ($user->is_editor) {
            $editorId = $user->id;
        } elseif ($user->is_staff) {
            $editorId = $user->editor_id;
        } else {
            $editorId = $user->id;
        }
        $products = Product::where('editor_id', $editorId)->orderBy('name')->get();
        $tables = Table::where('editor_id', $editorId)->orderBy('table_number')->get();
        
        // Get the table ID from the query parameter
        $selectedTableId = request()->query('table');
        
        // Validate the table ID if provided
        if ($selectedTableId) {
            $table = Table::find($selectedTableId);
            if (!$table || $table->editor_id != $editorId) {
                return redirect()->route('orders.create')->with('error', 'Invalid table selected.');
            }
        }
        
        $currentEditorId = $editorId;
        return view('orders.create', compact('products', 'tables', 'selectedTableId', 'currentEditorId'));
    }

    /**
     * Show the order creation page or handle table link redirection.
     */
    public function orderEntry(Request $request)
    {
        $user = Auth::user();
        $tableId = $request->query('table');
        if ($tableId) {
            // If table param is present, use the redirection logic
            return $this->handleTableLink($request);
        }
        if ($user->is_editor) {
            $editorId = $user->id;
        } elseif ($user->is_staff) {
            $editorId = $user->editor_id;
        } else {
            $editorId = $user->id;
        }
        $products = Product::where('editor_id', $editorId)->orderBy('name')->get();
        $tables = Table::where('editor_id', $editorId)->orderBy('table_number')->get();
        $selectedTableId = null;
        $currentEditorId = $editorId;
        return view('orders.create', compact('products', 'tables', 'selectedTableId', 'currentEditorId'));
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'products' => 'required|array',
            'products.*' => 'integer|min:0',
        ]);
        
        // Check if any products were ordered
        $hasProducts = false;
        foreach ($validated['products'] as $quantity) {
            if ($quantity > 0) {
                $hasProducts = true;
                break;
            }
        }
        
        if (!$hasProducts) {
            return redirect()->back()->withErrors(['products' => 'Please select at least one product.']);
        }
        
        // Get the table
        $table = Table::findOrFail($validated['table_id']);
        
        // Only restrict if user is an editor
        if ($user && $user->is_editor && $table->editor_id != $user->id) {
            abort(403);
        }
        
        // Find the current open TableSession for this table
        $currentSession = \App\Models\TableSession::where('table_id', $table->id)
            ->whereIn('status', ['open', 'reopened'])
            ->latest('opened_at')
            ->first();
        
        if (!$currentSession) {
            return redirect()->back()->withErrors(['table_id' => 'No open session for this table. Please open the table first.']);
        }
        
        // Assign editor_id: admin uses table's editor, editor uses own, guest uses table's editor
        $editorId = $user && $user->is_admin ? $table->editor_id : ($user ? $user->id : $table->editor_id);
        
        // Create the order
        $order = Order::create([
            'table_id' => $table->id,
            'table_session_id' => $currentSession->id,
            'status' => 'pending',
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
            'editor_id' => $editorId,
        ]);
        
        // Calculate total amount and create order items
        $totalAmount = 0;
        $itemIndex = 0;
        
        foreach ($validated['products'] as $productId => $quantity) {
            if ($quantity > 0) {
                $product = Product::findOrFail($productId);
                $price = $product->price;
                $totalAmount += $quantity * $price;
                
                // Create individual order items for each unit
                for ($i = 0; $i < $quantity; $i++) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'quantity' => 1,
                        'price' => $price,
                        'is_paid' => false,
                        'item_index' => $itemIndex++
                    ]);
                }
            }
        }
        
        // Update order total
        $order->update([
            'total_amount' => $totalAmount,
            'amount_left' => $totalAmount
        ]);
        
        // Update table order count
        $table->update([
            'orders' => $table->orders + 1,
            'status' => 'open', // Set status to open when a new order is added
        ]);
        
        return redirect()->route('orders.confirmation')->with('success', 'Your order has been submitted!');
    }
    
    /**
     * Display order confirmation page.
     */
    public function confirmation()
    {
        return view('orders.confirmation');
    }
    
    /**
     * Update the specified order.
     */
    public function update(Request $request, Order $order)
    {
        $user = Auth::user();
        if (!$user->is_admin && !($user->is_editor && $order->editor_id == $user->id)) {
            abort(403);
        }
        
        $validated = $request->validate([
            'status' => 'required|in:pending,delivered,completed,cancelled',
        ]);
        
        $order->update([
            'status' => $validated['status']
        ]);
        
        return redirect()->route('orders.index')->with('success', 'Order status updated successfully!');
    }
    
    /**
     * Get order data for AJAX requests.
     */
    public function getOrderData(Order $order)
    {
        return response()->json($order);
    }
    
    /**
     * Update an order via AJAX request.
     */
    public function updateOrder(Request $request, Order $order)
    {
        $user = Auth::user();
        if (!$user->is_admin && !($user->is_editor && $order->editor_id == $user->id)) {
            abort(403);
        }
        
        // Handle status update if status parameter is present
        if ($request->has('status')) {
            $validated = $request->validate([
                'status' => 'required|in:pending,delivered,completed,cancelled',
            ]);
            
            $order->update([
                'status' => $validated['status']
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully!'
            ]);
        }
        
        // Handle full order update if products parameter is present
        $validated = $request->validate([
            'table_id' => 'required|exists:tables,id',
            'products' => 'required|array',
            'products.*' => 'integer|min:0',
        ]);
        
        // Check if any products were ordered
        $hasProducts = false;
        foreach ($validated['products'] as $quantity) {
            if ($quantity > 0) {
                $hasProducts = true;
                break;
            }
        }
        
        if (!$hasProducts) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one product.'
            ]);
        }
        
        // Update order table_id while preserving status
        $currentStatus = $order->status; // Save current status
        $order->table_id = $validated['table_id'];
        $order->save();
        
        // Reset all product quantities
        for ($i = 1; $i <= 9; $i++) {
            $order->update([
                "product{$i}_qty" => 0
            ]);
        }
        
        // Update product quantities for the order
        foreach ($validated['products'] as $productId => $quantity) {
            if ($quantity > 0) {
                $order->update([
                    "product{$productId}_qty" => $quantity
                ]);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully!'
        ]);
    }
    
    /**
     * Display order archives page with XML files.
     *
     * Implementation plan:
     * - Only allow access to editors (abort 403 otherwise)
     * - Only show files belonging to the current editor (by folder or filename)
     * - Files should be stored as: storage/app/public/archive/{editor_id}/orders_{editor_id}_YYYY-MM-DD_HH-ii-ss.xml
     * - If not, filter by filename: orders_{editor_id}_*.xml
     */
    public function archive()
    {
        $user = Auth::user();
        if (!$user->is_editor) {
            abort(403);
        }
        $editorId = $user->id;
        $archiveDir = storage_path('app/public/archive/' . $editorId);
        $files = [];
        if (file_exists($archiveDir)) {
            $xmlFiles = glob($archiveDir . '/orders_' . $editorId . '_*.xml');
            foreach ($xmlFiles as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $lastModified = filemtime($file);
                preg_match('/orders_\\d{1,}_([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2}-[0-9]{2}-[0-9]{2})\\.xml/', $filename, $matches);
                $date = isset($matches[1]) ? $matches[1] : '';
                $time = isset($matches[2]) ? str_replace('-', ':', $matches[2]) : '';
                $files[] = [
                    'name' => $filename,
                    'path' => 'storage/archive/' . $editorId . '/' . $filename,
                    'size' => $this->formatFileSize($size),
                    'last_modified' => date('Y-m-d H:i:s', $lastModified),
                    'date' => $date,
                    'time' => $time
                ];
            }
            usort($files, function($a, $b) {
                return strtotime($b['last_modified']) - strtotime($a['last_modified']);
            });
        }
        return view('orders.archive', compact('files'));
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Handle permanent table link and redirect to unique token link if open.
     */
    public function handleTableLink(Request $request)
    {
        $tableId = $request->query('table');
        if (!$tableId) {
            abort(404, 'Table not specified.');
        }
        $table = Table::where('id', $tableId)->first();
        if (!$table) {
            abort(404, 'Table not found.');
        }
        if ($table->status === 'open' && $table->unique_token) {
            // Redirect to the unique token link
            return redirect()->route('order.redirect', ['unique_token' => $table->unique_token]);
        }
        // Table is closed or no token: show a message
        return response()->view('orders.table-closed', ['table' => $table]);
    }

    /**
     * Handle QR code entry: set table to pending_approval and show waiting page, or redirect if open.
     */
    public function qrEntry($editorname, $table_number)
    {
        $editor = User::where('username', $editorname)->first();
        if (!$editor) {
            abort(404, 'Editor not found.');
        }
        $table = Table::where('editor_id', $editor->id)
            ->where('table_number', $table_number)
            ->first();
        if (!$table) {
            abort(404, 'Table not found.');
        }
        $ip = request()->ip();
        // If table is open and has a unique token, handle session request
        if ($table->status === 'open' && $table->unique_token) {
            $currentSession = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
            if ($currentSession) {
                // Check if this IP is already pending or approved
                $existingRequest = $currentSession->sessionRequests()->where('ip_address', $ip)->whereIn('status', ['pending', 'approved'])->first();
                if (!$existingRequest) {
                    \App\Models\TableSessionRequest::create([
                        'table_session_id' => $currentSession->id,
                        'ip_address' => $ip,
                        'status' => 'pending',
                        'requested_at' => now(),
                    ]);
                }
                // If already approved, redirect to order page
                $approved = $currentSession->sessionRequests()->where('ip_address', $ip)->where('status', 'approved')->exists();
                if ($approved) {
                    return redirect()->route('order.redirect', ['unique_token' => $table->unique_token]);
                }
            }
            // If not approved, show waiting page
            return view('orders.waiting-approval', ['table' => $table]);
        }
        // If table is closed or pending, set to pending_approval if not already
        if ($table->status !== 'pending_approval') {
            $table->status = 'pending_approval';
            $table->save();
        }
        // Do NOT create a TableSessionRequest until the table is open and a session exists
        return view('orders.waiting-approval', ['table' => $table]);
    }

    /**
     * Polling endpoint for table status: returns status and redirect URL if open.
     */
    public function pollTableStatus($tableId)
    {
        $table = \App\Models\Table::find($tableId);
        if (!$table) {
            return response()->json(['status' => 'not_found']);
        }
        
        if ($table->status === 'open') {
            // Generate unique_token if missing
            if (!$table->unique_token) {
                $table->generateUniqueToken();
            }
            $user = Auth::user();
            if ($user && ($user->is_admin || $user->is_editor || $user->is_staff)) {
                // Authenticated users: allow immediate access
                return response()->json([
                    'status' => 'open',
                    'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                ]);
            } else {
                // Guest: require IP approval
                $ip = request()->ip();
                $currentSession = $table->sessions()->whereIn('status', ['open', 'reopened'])->latest('opened_at')->first();
                if ($currentSession) {
                    $approved = $currentSession->sessionRequests()->where('ip_address', $ip)->where('status', 'approved')->exists();
                    if ($approved) {
                        return response()->json([
                            'status' => 'open',
                            'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                        ]);
                    }
                }
                // Not approved: keep waiting
                return response()->json(['status' => 'waiting_ip_approval']);
            }
        }
        
        return response()->json(['status' => $table->status]);
    }

    /**
     * Polling endpoint for customer waiting page: returns order status and redirect URL if approved.
     */
    public function pollOrderStatus($orderId)
    {
        $order = \App\Models\Order::find($orderId);
        if ($order && $order->status === 'approved') {
            $table = \App\Models\Table::find($order->table_id);
            if ($table && $table->unique_token) {
                return response()->json([
                    'status' => 'approved',
                    'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
                ]);
            }
        }
        return response()->json(['status' => $order ? $order->status : 'not_found']);
    }
}