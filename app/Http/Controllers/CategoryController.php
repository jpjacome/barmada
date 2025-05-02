<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->is_admin && !$user->is_editor) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        $data = $validated;

        if ($user->is_admin) {
            $data['editor_id'] = $request->input('editor_id', null); // Admin can set or leave null
        } else {
            $data['editor_id'] = $user->id;
        }

        $category = Category::create($data);

        return redirect()->back();
    }

    public function destroy(Category $category)
    {
        $user = Auth::user();
        if (!$user->is_admin && !($user->is_editor && $category->editor_id == $user->id)) {
            abort(403);
        }

        $category->delete();

        return redirect()->back();
    }

    public function moveUp(Category $category)
    {
        $user = Auth::user();
        if (!$user->is_admin && !($user->is_editor && $category->editor_id == $user->id)) {
            abort(403);
        }

        $previousCategory = Category::where('sort_order', '<', $category->sort_order)
            ->where('editor_id', $user->is_admin ? '!=' : '=', $user->id)
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
        $user = Auth::user();
        if (!$user->is_admin && !($user->is_editor && $category->editor_id == $user->id)) {
            abort(403);
        }

        $nextCategory = Category::where('sort_order', '>', $category->sort_order)
            ->where('editor_id', $user->is_admin ? '!=' : '=', $user->id)
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
