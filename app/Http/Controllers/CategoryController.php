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
        $this->authorize('create', Category::class);

        $editorId = $user->is_admin ? $request->input('editor_id', null) : $user->effectiveEditorId();
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('categories')->where(function ($query) use ($editorId) {
                    return $query->where('editor_id', $editorId);
                }),
            ],
        ]);

        app(\App\Actions\Categories\CreateCategory::class)->handle((int) $editorId, $validated['name']);

        return redirect()->back();
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        app(\App\Actions\Categories\DeleteCategory::class)->handle($category);

        return redirect()->back();
    }

    public function moveUp(Category $category)
    {
        $this->authorize('update', $category);

        // Swap only within the category's own tenant.
        app(\App\Actions\Categories\MoveCategory::class)->handle($category, 'up');

        return redirect()->back();
    }

    public function moveDown(Category $category)
    {
        $this->authorize('update', $category);

        // Swap only within the category's own tenant.
        app(\App\Actions\Categories\MoveCategory::class)->handle($category, 'down');

        return redirect()->back();
    }
}
