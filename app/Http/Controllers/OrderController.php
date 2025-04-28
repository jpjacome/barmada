<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request)
    {
        $sort = $request->query('sort');
        $direction = $request->query('direction', 'asc');
        
        // Get all pending orders for the latest orders panel
        $pendingOrders = Order::with('table')->where('status', 'pending')->latest()->get();
        
        // Start with a base query for all orders
        $query = Order::with('table');
        
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
        $products = Product::orderBy('name')->get();
        $tables = Table::all();
        
        // Get the table ID from the query parameter
        $selectedTableId = request()->query('table');
        
        // Validate the table ID if provided
        if ($selectedTableId) {
            $table = Table::find($selectedTableId);
            if (!$table) {
                return redirect()->route('orders.create')->with('error', 'Invalid table selected.');
            }
        }
        
        return view('orders.create', compact('products', 'tables', 'selectedTableId'));
    }

    /**
     * Show the order creation page or handle table link redirection.
     */
    public function orderEntry(Request $request)
    {
        $tableId = $request->query('table');
        if ($tableId) {
            // If table param is present, use the redirection logic
            return $this->handleTableLink($request);
        }
        // Otherwise, show the generic order creation page
        $products = Product::orderBy('name')->get();
        $tables = Table::all();
        $selectedTableId = null;
        return view('orders.create', compact('products', 'tables', 'selectedTableId'));
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request)
    {
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
        
        // Create the order
        $order = Order::create([
            'table_id' => $table->id,
            'status' => 'pending',
            'total_amount' => 0,
            'amount_paid' => 0,
            'amount_left' => 0,
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
     */
    public function archive()
    {
        $archiveDir = storage_path('app/public/archive');
        $files = [];
        
        if (file_exists($archiveDir)) {
            // Get all XML files from the archive directory
            $xmlFiles = glob($archiveDir . '/*.xml');
            
            // Format file information
            foreach ($xmlFiles as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $lastModified = filemtime($file);
                
                // Parse the date from the filename (format: orders_YYYY-MM-DD_HH-ii-ss.xml)
                preg_match('/orders_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.xml/', $filename, $matches);
                $date = isset($matches[1]) ? $matches[1] : '';
                $time = isset($matches[2]) ? str_replace('-', ':', $matches[2]) : '';
                
                $files[] = [
                    'name' => $filename,
                    'path' => 'storage/archive/' . $filename,
                    'size' => $this->formatFileSize($size),
                    'last_modified' => date('Y-m-d H:i:s', $lastModified),
                    'date' => $date,
                    'time' => $time
                ];
            }
            
            // Sort files by last modified time (newest first)
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
    public function qrEntry($tableId)
    {
        $table = \App\Models\Table::findOrFail($tableId);
        if ($table->status === 'open' && $table->unique_token) {
            // Table is already open, redirect to order page
            return redirect()->route('order.redirect', ['unique_token' => $table->unique_token]);
        }
        if ($table->status !== 'pending_approval') {
            $table->status = 'pending_approval';
            $table->save();
        }
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
            return response()->json([
                'status' => 'open',
                'redirect_url' => route('order.redirect', ['unique_token' => $table->unique_token])
            ]);
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