<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        $category = Category::create($validated);

        return redirect()->back();
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->back();
    }

    public function moveUp(Category $category)
    {
        $previousCategory = Category::where('sort_order', '<', $category->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previousCategory) {
            $tempOrder = $category->sort_order;
            $category->sort_order = $previousCategory->sort_order;
            $previousCategory->sort_order = $tempOrder;

            $category->save();
            $previousCategory->save();
        }

        return redirect()->back();
    }

    public function moveDown(Category $category)
    {
        $nextCategory = Category::where('sort_order', '>', $category->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($nextCategory) {
            $tempOrder = $category->sort_order;
            $category->sort_order = $nextCategory->sort_order;
            $nextCategory->sort_order = $tempOrder;

            $category->save();
            $nextCategory->save();
        }

        return redirect()->back();
    }
}
