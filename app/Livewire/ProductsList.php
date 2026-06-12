<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Models\Category;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProductsList extends Component
{
    use AuthorizesRequests, WithFileUploads;
    
    public $products = [];
    public $categories = [];
    public $lastUpdated;
    public $status = 'Loading products...';
    public $refreshInterval = 10; // in seconds
    
    public $sortField = 'name';
    public $sortDirection = 'asc';

    // Form properties
    public $showProductModal = false;
    public $editMode = false;
    public $productId = null;
    
    #[Rule('required|min:3|max:255')]
    public $name = '';
    
    #[Rule('required|numeric|min:0.01')]
    public $price = '';
    
    public $iconType = 'bootstrap';
    
    #[Rule('required_if:iconType,bootstrap')]
    public $bootstrapIcon = 'bi-box';
    
    #[Rule('nullable|file|max:1024|required_if:iconType,svg')]
    public $svgFile;
    
    #[Rule('nullable|exists:categories,id')]
    public $categoryId;

    public $iconValue = '';
    public $categoryName = '';
    public $description = '';
    #[Rule('nullable|file|max:1024')]
    public $photoFile;
    public $photo = '';
    
    // Protected listeners for Livewire events
    protected $listeners = ['deleteConfirmed' => 'deleteConfirmed', 'categoryAdded' => 'loadCategories'];

    public function mount()
    {
        $this->loadProducts();
        $this->loadCategories();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->loadProducts();
    }

    public function loadProducts()
    {
        // Sorting props are client-controlled; constrain before querying.
        if (! in_array($this->sortField, ['name', 'price', 'created_at', 'updated_at', 'category_id'], true)) {
            $this->sortField = 'name';
        }
        $this->sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $user = Auth::user();
        if ($user->is_admin) {
            $this->products = Product::with('category')
                ->orderBy($this->sortField, $this->sortDirection)
                ->get();
        } else if ($user->is_editor) {
            $this->products = Product::with('category')
                ->where('editor_id', $user->id)
                ->orderBy($this->sortField, $this->sortDirection)
                ->get();
        } else {
            $this->products = collect();
        }
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Products updated at ' . $this->lastUpdated;
    }

    public function loadCategories()
    {
        $user = Auth::user();
        if ($user->is_admin) {
            $this->categories = Category::orderBy('name')->get();
        } else if ($user->is_editor) {
            $this->categories = Category::where('editor_id', $user->id)->orderBy('name')->get();
        } else {
            $this->categories = collect();
        }
    }

    #[On('refresh-products')]
    public function refreshProducts()
    {
        $this->loadProducts();
    }
    
    // Create or update a product — persistence and the upload security
    // gates live in the shared SaveProduct action (same code path as
    // the API); this method keeps the form validation and modal flow.
    public function saveProduct()
    {
        $user = Auth::user();
        if ($this->iconType === 'bootstrap') {
            $this->validate([
                'name' => ['required', 'min:3', 'max:255', 'regex:/^[^<>]*$/'],
                'price' => 'required|numeric|min:0.01',
                'bootstrapIcon' => ['required', 'regex:/^[a-z0-9 -]+$/i'],
            ]);
        } else {
            $this->validate([
                'name' => ['required', 'min:3', 'max:255', 'regex:/^[^<>]*$/'],
                'price' => 'required|numeric|min:0.01',
                'svgFile' => 'nullable|file|max:1024',
            ]);
        }

        if ($this->photoFile) {
            $this->validate([
                'photoFile' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:1024'],
            ]);
        }

        $data = [
            'name' => $this->name,
            'price' => $this->price,
            'icon_type' => $this->iconType,
            'bootstrap_icon' => $this->bootstrapIcon,
            'icon_value_fallback' => $this->iconValue,
            'category_id' => $this->categoryId,
            'description' => $this->description,
            'photo_fallback' => $this->photo,
        ];

        $product = null;
        if ($this->editMode) {
            $product = Product::findOrFail($this->productId);
            $this->authorize('update', $product);
        } else {
            $this->authorize('create', Product::class);
        }

        try {
            app(\App\Actions\Products\SaveProduct::class)->handle(
                $user,
                $data,
                $this->iconType === 'svg' ? $this->svgFile : null,
                $this->photoFile,
                $product,
            );
        } catch (\App\Exceptions\DomainActionException $e) {
            $this->addError('svgFile', $e->getMessage());

            return;
        }

        $this->status = $this->editMode
            ? "Product '{$this->name}' updated successfully!"
            : "Product '{$this->name}' added successfully!";

        $this->closeModal();
        $this->loadProducts();
    }

    public function saveCategory()
    {
        $user = Auth::user();
        $this->validate([
            'categoryName' => [
                'required',
                'min:3',
                'max:255',
                Rule::unique('categories', 'name')->where(function ($query) use ($user) {
                    return $query->where('editor_id', $user->id);
                }),
            ],
        ]);

        $this->authorize('create', Category::class);
        app(\App\Actions\Categories\CreateCategory::class)->handle(
            (int) ($user->is_admin ? $user->id : $user->effectiveEditorId()),
            $this->categoryName,
        );
        $this->categoryName = '';
        $this->loadCategories();
        $this->status = 'Category added successfully!';
    }

    public function deleteCategory($id)
    {
        $category = Category::find($id);

        if (!$category) {
            $this->status = 'Error: Category not found';
            return;
        }

        $this->authorize('delete', $category);
        $categoryName = $category->name;
        app(\App\Actions\Categories\DeleteCategory::class)->handle($category);
        $this->loadCategories();
        $this->status = "Category '{$categoryName}' deleted successfully!";
    }
    
    // Show modal for adding a new product
    public function addProduct()
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showProductModal = true;
    }
    
    // Show modal for editing a product
    public function editProduct($id)
    {
        $this->resetForm();
        $this->editMode = true;
        $this->productId = $id;
        
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);
        $this->name = $product->name;
        $this->price = $product->price;
        $this->iconType = $product->icon_type ?? 'bootstrap';
        
        if ($this->iconType === 'bootstrap') {
            $this->bootstrapIcon = $product->icon_value ?? 'bi-box';
        } else {
            $this->iconValue = $product->icon_value;
        }
        
        $this->categoryId = $product->category_id;
        $this->description = $product->description;
        $this->photo = $product->photo;
        $this->showProductModal = true;
    }
    
    // Show delete confirmation dialog
    public function confirmDelete($id)
    {
        $product = Product::findOrFail($id);
        $this->productId = $id;
        
        $this->dispatch('showDeleteConfirmation', [
            'title' => 'Delete Product',
            'message' => "Are you sure you want to delete the product '{$product->name}'?",
            'confirmButtonText' => 'Delete',
            'eraseAll' => false
        ]);
    }
    
    // Delete a product when called directly (from the button)
    public function deleteProduct($id = null)
    {
        $user = Auth::user();
        $productId = $id ?? $this->productId;
        
        if (!$productId) {
            $this->status = "Error: No product selected for deletion";
            return;
        }
        
        $product = Product::find($productId);
        
        if (!$product) {
            $this->status = "Error: Product not found";
            return;
        }
        
        $this->authorize('delete', $product);
        
        $productName = $product->name;
        $product->delete();
        
        $this->productId = null;
        $this->status = "Product '{$productName}' deleted successfully!";
        $this->loadProducts();
    }
    
    // Delete a product after confirmation
    public function deleteConfirmed()
    {
        $this->deleteProduct();
    }
    
    /**
     * 86 an item (or bring it back) during service. Unavailable products
     * show as sold out on the guest menu and are rejected server-side.
     */
    public function toggleAvailability($id)
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);
        app(\App\Actions\Products\ToggleProductAvailability::class)->handle($product);
        $this->status = $product->is_available
            ? "'{$product->name}' is available again."
            : "'{$product->name}' marked as sold out.";
        $this->loadProducts();
    }

    // Toggle icon type between bootstrap and svg
    public function toggleIconType()
    {
        $this->iconType = $this->iconType === 'bootstrap' ? 'svg' : 'bootstrap';
    }
    
    // Close the modal and reset form
    public function closeModal()
    {
        $this->showProductModal = false;
        $this->resetForm();
    }
    
    // Reset form fields
    private function resetForm()
    {
        $this->productId = null;
        $this->name = '';
        $this->price = '';
        $this->iconType = 'bootstrap';
        $this->bootstrapIcon = 'bi-box';
        $this->svgFile = null;
        $this->iconValue = '';
        $this->categoryId = null;
        $this->description = '';
        $this->photoFile = null;
        $this->photo = '';
        $this->resetValidation();
    }

    public function eraseAllProducts()
    {
        $user = Auth::user();
        if (! $user->is_admin && ! $user->is_editor) {
            abort(403);
        }
        // EditorScope bounds this to the caller's tenant; admins clear all
        // tenants explicitly through this action. No table truncation.
        $count = Product::count();
        Product::query()->delete();
        $this->status = $count . ' products deleted.';
        $this->loadProducts();
    }

    public function confirmEraseAll()
    {
        $count = Product::count();
        $this->dispatch('showDeleteConfirmation', [
            'message' => "Are you sure you want to erase ALL (" . $count . ") products? This action cannot be undone.",
            'eraseAll' => 'true'
        ]);
    }

    public function deleteAllConfirmed()
    {
        $user = Auth::user();
        if (! $user->is_admin && ! $user->is_editor) {
            abort(403);
        }
        $count = Product::count();
        Product::query()->delete();
        $this->status = $count . ' products deleted.';
        $this->loadProducts();
    }

    public function render()
    {
        $user = Auth::user();
        if ($user->is_admin) {
            $products = Product::with('category')->get();
        } else if ($user->is_editor) {
            $products = Product::with('category')->where('editor_id', $user->id)->get();
        } else {
            $products = collect();
        }
        $tenant = $user->is_admin ? $user : \App\Models\User::find($user->effectiveEditorId());
        return view('livewire.products-list', [
            'products' => $products,
            'currency' => $tenant ? $tenant->currencySymbol() : '$',
        ]);
    }
}