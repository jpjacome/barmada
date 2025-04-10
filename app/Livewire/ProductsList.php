<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\WithFileUploads;

class ProductsList extends Component
{
    use WithFileUploads;
    
    public $products = [];
    public $lastUpdated;
    public $status = 'Loading products...';
    public $refreshInterval = 10; // in seconds
    
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
    
    public $iconValue = '';
    
    // Protected listeners for Livewire events
    protected $listeners = ['deleteConfirmed' => 'deleteProduct'];

    public function mount()
    {
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $this->products = Product::orderBy('name')->get();
        $this->lastUpdated = now()->format('H:i:s');
        $this->status = 'Products updated at ' . $this->lastUpdated;
    }

    #[On('refresh-products')]
    public function refreshProducts()
    {
        $this->loadProducts();
    }
    
    // Create or update a product
    public function saveProduct()
    {
        if ($this->iconType === 'bootstrap') {
            $this->validate([
                'name' => 'required|min:3|max:255',
                'price' => 'required|numeric|min:0.01',
                'bootstrapIcon' => 'required',
            ]);
            $iconValue = $this->bootstrapIcon;
        } else {
            $this->validate([
                'name' => 'required|min:3|max:255',
                'price' => 'required|numeric|min:0.01',
                'svgFile' => 'required|file|max:1024',
            ]);
            
            // For SVG files specifically, we'll use our custom isSvg macro
            if ($this->svgFile->getClientOriginalExtension() === 'svg' && !$this->svgFile->isSvg()) {
                $this->addError('svgFile', 'The file must be a valid SVG image.');
                return;
            }
            
            // Store the image and get its filename
            $filename = $this->svgFile->store('product-icons', 'public');
            $iconValue = $filename;
        }
        
        if ($this->editMode) {
            $product = Product::findOrFail($this->productId);
            $product->update([
                'name' => $this->name,
                'price' => $this->price,
                'icon_type' => $this->iconType,
                'icon_value' => $iconValue,
            ]);
            $this->status = "Product '{$this->name}' updated successfully!";
        } else {
            Product::create([
                'name' => $this->name,
                'price' => $this->price,
                'icon_type' => $this->iconType,
                'icon_value' => $iconValue,
            ]);
            $this->status = "Product '{$this->name}' added successfully!";
        }
        
        $this->closeModal();
        $this->loadProducts();
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
        $this->name = $product->name;
        $this->price = $product->price;
        $this->iconType = $product->icon_type ?? 'bootstrap';
        
        if ($this->iconType === 'bootstrap') {
            $this->bootstrapIcon = $product->icon_value ?? 'bi-box';
        } else {
            $this->iconValue = $product->icon_value;
        }
        
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
        ]);
    }
    
    // Delete a product after confirmation
    public function deleteProduct()
    {
        $product = Product::findOrFail($this->productId);
        $productName = $product->name;
        
        $product->delete();
        
        $this->productId = null;
        $this->status = "Product '{$productName}' deleted successfully!";
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
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.products-list');
    }
} 