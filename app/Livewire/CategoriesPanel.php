<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'newCategoryName' => [
                'required',
                'min:3',
                'max:255',
                Rule::unique('categories', 'name')->where(function ($query) use ($user) {
                    return $query->where('editor_id', $user->id);
                }),
            ],
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
        $this->dispatch('categoryAdded');
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
        $this->resequenceSortOrder();
    }

    public function moveUp($id)
    {
        $category = Category::find($id);
        if (!$category) return;
        $user = Auth::user();
        $query = $user->is_admin
            ? Category::query()
            : Category::where('editor_id', $category->editor_id);
        $categories = $query->orderBy('sort_order')->get();
        $count = $categories->count();
        $index = $categories->search(fn($c) => $c->id == $category->id);
        if ($index === false) return;
        $swapIndex = $index === 0 ? $count - 1 : $index - 1;
        $swapCategory = $categories[$swapIndex];
        $tmp = $category->sort_order;
        $category->sort_order = $swapCategory->sort_order;
        $swapCategory->sort_order = $tmp;
        $category->save();
        $swapCategory->save();
        $this->resequenceSortOrder();
    }

    public function moveDown($id)
    {
        $category = Category::find($id);
        if (!$category) return;
        $user = Auth::user();
        $query = $user->is_admin
            ? Category::query()
            : Category::where('editor_id', $category->editor_id);
        $categories = $query->orderBy('sort_order')->get();
        $count = $categories->count();
        $index = $categories->search(fn($c) => $c->id == $category->id);
        if ($index === false) return;
        $swapIndex = $index === $count - 1 ? 0 : $index + 1;
        $swapCategory = $categories[$swapIndex];
        $tmp = $category->sort_order;
        $category->sort_order = $swapCategory->sort_order;
        $swapCategory->sort_order = $tmp;
        $category->save();
        $swapCategory->save();
        $this->resequenceSortOrder();
    }

    /**
     * Ensure all categories for the current editor have sequential sort_order values (1, 2, 3, ...)
     */
    public function resequenceSortOrder()
    {
        $user = Auth::user();
        $query = $user->is_admin
            ? Category::query()
            : Category::where('editor_id', $user->id);
        $categories = $query->orderBy('sort_order')->get();
        $i = 1;
        foreach ($categories as $category) {
            if ($category->sort_order != $i) {
                $category->sort_order = $i;
                $category->save();
            }
            $i++;
        }
        $this->loadCategories();
    }

    public function render()
    {
        return view('livewire.categories-panel');
    }
}
