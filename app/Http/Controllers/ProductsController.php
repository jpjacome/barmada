<?php

namespace App\Http\Controllers;

use App\Models\Category;

/**
 * The authenticated Products & Categories management page.
 * (Previously hosted on the legacy NumberController.)
 */
class ProductsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user && $user->is_admin) {
            $categories = Category::orderBy('sort_order')->get();
        } elseif ($user && $user->is_editor) {
            $categories = Category::where('editor_id', $user->id)->orderBy('sort_order')->get();
        } else {
            $categories = collect();
        }

        return view('products.livewire', compact('categories'));
    }
}
