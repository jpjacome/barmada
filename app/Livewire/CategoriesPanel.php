<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class CategoriesPanel extends Component
{
    public $categories = [];
    public $newCategoryName = '';
    public $status = '';

    public function mount()
    {
        $this->loadCategories();
    }

    public function loadCategories()
    {
        $user = Auth::user();
        if ($user->is_admin) {
            $this->categories = Category::orderBy('sort_order')->get();
        } else if ($user->is_editor) {
            $this->categories = Category::where('editor_id', $user->id)->orderBy('sort_order')->get();
        } else {
            $this->categories = collect();
        }
    }

    public function addCategory()
    {
        $user = Auth::user();
        $this->validate([
            'newCategoryName' => 'required|min:3|max:255|unique:categories,name',
        ]);
        $maxOrder = Category::where('editor_id', $user->id)->max('sort_order') ?? 0;
        Category::create([
            'name' => $this->newCategoryName,
            'editor_id' => $user->id,
            'sort_order' => $maxOrder + 1,
        ]);
        $this->newCategoryName = '';
        $this->status = 'Category added successfully!';
        $this->loadCategories();
    }

    public function deleteCategory($id)
    {
        $category = Category::find($id);
        if (!$category) {
            $this->status = 'Error: Category not found';
            return;
        }
        $category->delete();
        $this->status = 'Category deleted successfully!';
        $this->loadCategories();
    }

    public function moveUp($id)
    {
        $category = Category::find($id);
        if (!$category) return;
        $prev = Category::where('editor_id', $category->editor_id)
            ->where('sort_order', '<', $category->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();
        if ($prev) {
            $tmp = $category->sort_order;
            $category->sort_order = $prev->sort_order;
            $prev->sort_order = $tmp;
            $category->save();
            $prev->save();
            $this->status = 'Category moved up.';
            $this->loadCategories();
        }
    }

    public function moveDown($id)
    {
        $category = Category::find($id);
        if (!$category) return;
        $next = Category::where('editor_id', $category->editor_id)
            ->where('sort_order', '>', $category->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();
        if ($next) {
            $tmp = $category->sort_order;
            $category->sort_order = $next->sort_order;
            $next->sort_order = $tmp;
            $category->save();
            $next->save();
            $this->status = 'Category moved down.';
            $this->loadCategories();
        }
    }

    public function render()
    {
        return view('livewire.categories-panel');
    }
}
