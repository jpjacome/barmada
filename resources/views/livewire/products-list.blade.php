<div class="products-container">
    <!-- Link to the external CSS file -->
    <link href="{{ asset('css/products-list.css') }}" rel="stylesheet">
    
    <div class="products-main py-4">
        <div class="products-header">
            <h3 class="products-title">Products</h3>
        </div>
        
        <div 
            wire:poll.{{ $refreshInterval }}s="refreshProducts"
            class="products-data mt-4"
        >
            <div class="products-table-container">
                <table class="products-table">
                    <thead class="products-table-header">
                        <tr>
                            <th scope="col" class="products-table-header-cell">Name</th>
                            <th scope="col" class="products-table-header-cell text-right">Price</th>
                            <th scope="col" class="products-table-header-cell text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="products-table-body">
                        @forelse ($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="product">
                                <td class="product-cell product-name-cell">
                                    @if($product->icon_type === 'bootstrap')
                                        <i class="{{ $product->icon_value ?? 'bi-box' }} product-icon"></i>
                                    @else
                                        <img src="{{ asset('storage/' . $product->icon_value) }}" alt="{{ $product->name }}" class="product-icon-image">
                                    @endif
                                    <span class="product-name">{{ $product->name }}</span>
                                </td>
                                <td class="product-cell product-price">
                                    ${{ number_format($product->price, 2) }}
                                </td>
                                <td class="product-cell product-actions">
                                    <button 
                                        wire:click="editProduct({{ $product->id }})" 
                                        class="product-edit-button"
                                        title="Edit Product"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button 
                                        wire:click="deleteProduct({{ $product->id }})" 
                                        class="product-delete-button"
                                        title="Delete Product"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="product-empty-message">
                                    No products found. Add your first product to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="products-footer">
            <button 
                wire:click="addProduct" 
                class="products-add-button"
            >
                <i class="bi bi-plus-circle mr-1"></i> Add Product
            </button>
        </div>
    </div>
    
    <div class="products-footer">
        <div class="products-refresh-info">Auto-refresh every {{ $refreshInterval }} seconds.</div>
        <div class="products-last-updated">Last updated: {{ $lastUpdated }}</div>
    </div>

    <!-- Product Modal -->
    @if($showProductModal)
    <div class="product-modal-overlay">
        <div class="product-modal">
            <div class="product-modal-header">
                <h3 class="product-modal-title">
                    {{ $editMode ? 'Edit Product' : 'Add New Product' }}
                </h3>
            </div>
            <form wire:submit.prevent="saveProduct">
                <div class="product-modal-body">
                    <!-- Product Name -->
                    <div class="product-form-group">
                        <label for="name" class="product-form-label">
                            Product Name
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            wire:model="name" 
                            class="product-form-input"
                            placeholder="Enter product name"
                        >
                        @error('name') 
                            <span class="product-form-error">{{ $message }}</span> 
                        @enderror
                    </div>
                    
                    <!-- Product Price -->
                    <div class="product-form-group">
                        <label for="price" class="product-form-label">
                            Price ($)
                        </label>
                        <input 
                            type="number" 
                            id="price" 
                            wire:model="price" 
                            class="product-form-input"
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                        >
                        @error('price') 
                            <span class="product-form-error">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Icon Type Selection -->
                    <div class="product-form-group">
                        <label class="product-form-label">
                            Icon Type
                        </label>
                        <div class="product-icon-type-options">
                            <label class="product-icon-type-option">
                                <input 
                                    type="radio" 
                                    wire:model="iconType" 
                                    value="bootstrap" 
                                    class="product-icon-type-radio"
                                >
                                <span class="product-icon-type-label">Bootstrap Icon</span>
                            </label>
                            <label class="product-icon-type-option">
                                <input 
                                    type="radio" 
                                    wire:model="iconType" 
                                    value="svg" 
                                    class="product-icon-type-radio"
                                >
                                <span class="product-icon-type-label">Upload Image</span>
                            </label>
                        </div>
                    </div>

                    <!-- Bootstrap Icon Selection -->
                    @if($iconType === 'bootstrap')
                    <div class="product-form-group">
                        <label for="bootstrapIcon" class="product-form-label">
                            Bootstrap Icon Class
                        </label>
                        <div class="product-bootstrap-icon-container">
                            <div class="product-bootstrap-icon-input">
                                <div class="product-bootstrap-icon-preview">
                                    <i class="{{ $bootstrapIcon }} product-bootstrap-icon"></i>
                                </div>
                                <input 
                                    type="text" 
                                    id="bootstrapIcon" 
                                    wire:model="bootstrapIcon" 
                                    class="product-bootstrap-icon-field"
                                    placeholder="bi-box"
                                >
                            </div>
                            <small class="product-bootstrap-icon-help">
                                Enter a valid Bootstrap icon class (e.g., bi-cup, bi-cart, bi-egg-fried)
                            </small>
                            <a href="https://icons.getbootstrap.com/" target="_blank" class="product-bootstrap-icon-link">
                                Browse available icons on Bootstrap Icons website
                            </a>
                            @error('bootstrapIcon') 
                                <span class="product-form-error">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>
                    @else
                    <!-- SVG Upload -->
                    <div class="product-form-group">
                        <label for="svgFile" class="product-form-label">
                            Upload Icon Image
                        </label>
                        <div 
                            x-data="{ isUploading: false, progress: 0 }"
                            x-on:livewire-upload-start="isUploading = true"
                            x-on:livewire-upload-finish="isUploading = false"
                            x-on:livewire-upload-error="isUploading = false"
                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                            class="product-file-upload"
                        >
                            <label class="product-file-upload-label">
                                <span class="sr-only">Choose file</span>
                                <input 
                                    type="file" 
                                    id="svgFile" 
                                    wire:model="svgFile" 
                                    class="product-file-upload-input"
                                    accept=".jpg,.jpeg,.png,.svg,.webp"
                                >
                            </label>
                            <!-- Progress Bar -->
                            <div x-show="isUploading" class="product-upload-progress-container">
                                <div class="product-upload-progress-bar">
                                    <div class="product-upload-progress-value" x-bind:style="'width: ' + progress + '%'"></div>
                                </div>
                            </div>
                            <!-- File Preview -->
                            @if($svgFile)
                                <div class="product-file-preview">
                                    <img src="{{ $svgFile->temporaryUrl() }}" alt="Preview" class="product-file-preview-image">
                                </div>
                            @elseif($iconValue && $iconType === 'svg' && $editMode)
                                <div class="product-file-preview">
                                    <img src="{{ asset('storage/' . $iconValue) }}" alt="Current Icon" class="product-file-preview-image">
                                    <span class="product-file-preview-label">Current icon</span>
                                </div>
                            @endif
                            <small class="product-file-upload-help">
                                Upload JPG, PNG, SVG, or WebP (max 1MB). Square images work best.
                            </small>
                            @error('svgFile') 
                                <span class="product-form-error">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>
                    @endif
                </div>
                <div class="product-modal-footer">
                    <button 
                        type="button" 
                        wire:click="closeModal" 
                        class="product-cancel-button"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="product-submit-button"
                    >
                        {{ $editMode ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation - uses JS -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            @this.on('showDeleteConfirmation', (event) => {
                if (confirm(event.message)) {
                    @this.dispatch('deleteConfirmed');
                }
            });
        });
    </script>
</div> 