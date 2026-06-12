<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\MoveCategory;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu categories with the venue's manual ordering. Implicit binding
 * resolves through EditorScope (cross-tenant ids 404); policies
 * authorize every mutation.
 */
class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return response()->json([
            'categories' => Category::withCount('products')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category) => $this->categoryRow($category)),
        ]);
    }

    public function store(Request $request, CreateCategory $createCategory)
    {
        $this->authorize('create', Category::class);

        $user = $request->user();
        $editorId = (int) ($user->is_admin ? $user->id : $user->effectiveEditorId());

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:255',
                Rule::unique('categories', 'name')->where(fn ($query) => $query->where('editor_id', $editorId)),
            ],
        ]);

        $category = $createCategory->handle($editorId, $validated['name']);

        return response()->json(['category' => $this->categoryRow($category)], 201);
    }

    /**
     * Rename a category (unique within its tenant).
     */
    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:255',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('editor_id', $category->editor_id))
                    ->ignore($category->id),
            ],
        ]);

        $category->update(['name' => $validated['name']]);

        return response()->json(['category' => $this->categoryRow($category->refresh())]);
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory)
    {
        $this->authorize('delete', $category);

        $deleteCategory->handle($category);

        return response()->json(['message' => __('Category deleted.')]);
    }

    /**
     * Move one step in the manual ordering: {"direction": "up"|"down"}.
     * Edges are no-ops.
     */
    public function move(Request $request, Category $category, MoveCategory $moveCategory)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $moveCategory->handle($category, $validated['direction']);

        return response()->json([
            'categories' => Category::forEditor($category->editor_id)
                ->withCount('products')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $row) => $this->categoryRow($row)),
        ]);
    }

    private function categoryRow(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => (int) $category->sort_order,
            'products_count' => (int) ($category->products_count ?? $category->products()->count()),
        ];
    }
}
