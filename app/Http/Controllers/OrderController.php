<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
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
        
        return view('orders.create', compact('products', 'tables'));
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
        ]);
        
        // Update product quantities for the order
        foreach ($validated['products'] as $productId => $quantity) {
            if ($quantity > 0) {
                $order->update([
                    "product{$productId}_qty" => $quantity
                ]);
            }
        }
        
        // Update table order count
        $table->update([
            'orders' => $table->orders + 1,
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
} 