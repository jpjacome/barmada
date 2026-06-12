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
    
    // Create or update a product
    public function saveProduct()
    {
        $user = Auth::user();
        if ($this->iconType === 'bootstrap') {
            $this->validate([
                'name' => ['required', 'min:3', 'max:255', 'regex:/^[^<>]*$/'],
                'price' => 'required|numeric|min:0.01',
                'bootstrapIcon' => ['required', 'regex:/^[a-z0-9 -]+$/i'],
            ]);
            $iconValue = $this->bootstrapIcon;
        } else {
            $this->validate([
                'name' => ['required', 'min:3', 'max:255', 'regex:/^[^<>]*$/'],
                'price' => 'required|numeric|min:0.01',
                'svgFile' => 'nullable|file|max:1024',
            ]);

            if ($this->svgFile) {
                // Only accept genuine, script-free SVGs, and store under a
                // random name with a forced .svg extension so an attacker
                // cannot land an executable file (e.g. .php) on the public
                // disk via a crafted original filename.
                $original = strtolower((string) $this->svgFile->getClientOriginalExtension());
                $contents = @file_get_contents($this->svgFile->getRealPath());
                if ($original !== 'svg' || $contents === false || ! $this->isSafeSvg($contents)) {
                    $this->addError('svgFile', 'The icon must be a valid SVG with no scripts or embedded content.');
                    return;
                }
                $iconValue = $this->svgFile->storeAs(
                    'product-icons',
                    \Illuminate\Support\Str::random(40) . '.svg',
                    'public'
                );
            } else {
                // Retain the existing icon value if no new file is uploaded
                $iconValue = $this->iconValue;
            }
        }

        // Handle photo upload — accept only real raster images and force a
        // safe, fixed extension on the stored filename.
        if ($this->photoFile) {
            $this->validate([
                'photoFile' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:1024'],
            ]);
            $photoExtensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
            ];
            $photoExt = $photoExtensionMap[$this->photoFile->getMimeType()] ?? 'jpg';
            $photoPath = $this->photoFile->storeAs(
                'product-photos',
                \Illuminate\Support\Str::random(40) . '.' . $photoExt,
                'public'
            );
        } else {
            $photoPath = $this->photo;
        }

        // Category assignment must resolve within the caller's tenant.
        if ($this->categoryId !== null && $this->categoryId !== '' && ! Category::find($this->categoryId)) {
            $this->categoryId = null;
        }

        if ($this->editMode) {
            $product = Product::findOrFail($this->productId);
            $this->authorize('update', $product);
            $product->update([
                'name' => $this->name,
                'price' => $this->price,
                'icon_type' => $this->iconType,
                'icon_value' => $iconValue,
                'category_id' => $this->categoryId,
                'description' => $this->description,
                'photo' => $photoPath,
            ]);
            $this->status = "Product '{$this->name}' updated successfully!";
        } else {
            $data = [
                'name' => $this->name,
                'price' => $this->price,
                'icon_type' => $this->iconType,
                'icon_value' => $iconValue,
                'category_id' => $this->categoryId,
                'description' => $this->description,
                'photo' => $photoPath,
            ];
            $this->authorize('create', Product::class);
            if ($user->is_admin) {
                $data['editor_id'] = $user->id;
            }
            // Non-admins: BelongsToEditor assigns the tenant automatically.
            Product::create($data);
            $this->status = "Product '{$this->name}' added successfully!";
        }
        
        $this->closeModal();
        $this->loadProducts();
    }

    /**
     * Reject SVGs that carry active content. This is a lightweight gate
     * (not a full sanitizer): it blocks scripts, event handlers, external
     * entities and embeddings that could execute if the file were opened
     * directly in the browser.
     */
    private function isSafeSvg(string $svg): bool
    {
        $haystack = strtolower($svg);
        if (! str_contains($haystack, '<svg')) {
            return false;
        }
        $blocked = [
            '<script', 'javascript:', '<foreignobject', '<iframe',
            '<embed', '<object', '<!entity',
        ];
        foreach ($blocked as $needle) {
            if (str_contains($haystack, $needle)) {
                return false;
            }
        }
        // Any inline event handler such as onload= / onclick=.
        if (preg_match('/\son[a-z]+\s*=/i', $svg)) {
            return false;
        }
        return true;
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
        Category::create([
            'name' => $this->categoryName,
            'editor_id' => $user->is_admin ? $user->id : $user->effectiveEditorId(),
        ]);
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
        $category->delete();
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