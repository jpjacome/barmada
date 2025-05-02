<?php

namespace App\Http\Controllers;

use App\Events\NumberAdded;
use App\Models\Number;
use App\Models\Product;
use App\Models\Category; // Import the Category model
use Illuminate\Http\Request;

class NumberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $numbers = Number::latest()->get();
        return view('numbers.index', compact('numbers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('numbers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'value' => 'required|integer',
        ]);

        $number = Number::create($validated);
        
        try {
            // Broadcast the event
            event(new NumberAdded($number));
        } catch (\Exception $e) {
            // Log the error but continue
            logger()->error('Failed to broadcast event: ' . $e->getMessage());
        }
        
        return redirect()->route('numbers.create')->with('success', 'Number sent successfully!');
    }

    /**
     * Display the list of numbers with real-time updates
     */
    public function live()
    {
        $numbers = Number::latest()->get();
        return view('numbers.live', compact('numbers'));
    }
    
    /**
     * Display the list of products with Livewire real-time updates
     */
    public function livewire()
    {
        $user = auth()->user();
        if ($user && $user->is_admin) {
            $categories = Category::orderBy('sort_order')->get();
        } else if ($user && $user->is_editor) {
            $categories = Category::where('editor_id', $user->id)->orderBy('sort_order')->get();
        } else {
            // Guest (public/QR): show no categories or only public ones if you want
            $categories = collect();
        }
        return view('products.livewire', compact('categories'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
