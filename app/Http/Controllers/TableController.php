<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Product;
use Illuminate\Http\Request;

class TableController extends Controller
{
    /**
     * Display a listing of the tables.
     */
    public function index()
    {
        return view('tables.index');
    }

    /**
     * Show the form for creating a new table.
     */
    public function create()
    {
        return view('tables.create');
    }

    /**
     * Store a newly created table in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:tables,name',
            'capacity' => 'required|integer|min:1|max:20',
        ]);

        $table = Table::create([
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'is_occupied' => false,
        ]);
        
        return redirect()->route('tables.index')->with('success', 'Table created successfully!');
    }

    /**
     * Display the specified table.
     */
    public function show(Table $table)
    {
        return view('tables.show', compact('table'));
    }

    /**
     * Show the form for editing the specified table.
     */
    public function edit(Table $table)
    {
        return view('tables.edit', compact('table'));
    }

    /**
     * Update the specified table in storage.
     */
    public function update(Request $request, Table $table)
    {
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
        $products = Product::orderBy('name')->get();
        $tables = Table::all();
        $selectedTableId = $table->id;
        // Render the order creation view with the table preselected
        return view('orders.create', compact('products', 'tables', 'selectedTableId'));
    }
}