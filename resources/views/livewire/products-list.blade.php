@section('header')
    <h1 class="page-title">{{ __('Products & Categories') }}</h1>
@endsection
<div class="products-container">
    <!-- Link to component-specific CSS only, since general CSS is loaded in the layout -->
    <link href="{{ asset('css/products-list.css') }}" rel="stylesheet">
    
    <div class="products-main">
        
        

        <div 
            wire:poll.{{ $refreshInterval }}s="refreshProducts"
            class="products-data"
        >
            <div class="products-table-container">
                <table class="products-table">
                    <thead class="products-table-header">
                        <tr>
                            <th scope="col" class="products-table-header-cell cursor-pointer" wire:click="sortBy('name')">
                                {{ __('Name') }}
                                @if ($sortField === 'name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </th>
                            <th scope="col" class="products-table-header-cell cursor-pointer" wire:click="sortBy('category.name')">
                                {{ __('Category') }}
                                @if ($sortField === 'category.name')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </th>
                            <th scope="col" class="products-table-header-cell cursor-pointer" wire:click="sortBy('price')">
                                {{ __('Price') }}
                                @if ($sortField === 'price')
                                    <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </th>
                            <th scope="col" class="products-table-header-cell products-table-cell-right">{{ __('Actions') }}</th>
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
                                <td class="product-cell product-category">
                                    {{ $product->category->name ?? __('Uncategorized') }}
                                </td>
                                <td class="product-cell product-price">
                                    {{ $currency }}{{ number_format($product->price, 2) }}
                                    @unless($product->is_available)
                                        <span style="display:inline-block;margin-left:0.4rem;padding:0.1rem 0.5rem;border-radius:999px;background:var(--color-danger,#c0392b);color:#fff;font-size:0.7rem;font-weight:bold;vertical-align:middle;">{{ __('SOLD OUT') }}</span>
                                    @endunless
                                </td>
                                <td class="product-cell product-actions">
                                    <button
                                        wire:click="toggleAvailability({{ $product->id }})"
                                        class="product-edit-button"
                                        title="{{ $product->is_available ? __('Mark as sold out (86)') : __('Mark as available') }}"
                                        aria-label="{{ $product->is_available ? __('Mark as sold out') : __('Mark as available') }}"
                                    >
                                        <i class="bi {{ $product->is_available ? 'bi-eye' : 'bi-eye-slash' }}" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        wire:click="editProduct({{ $product->id }})"
                                        class="product-edit-button"
                                        title="{{ __('Edit Product') }}"
                                        aria-label="{{ __('Edit product') }}"
                                    >
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        wire:click="confirmDelete({{ $product->id }})"
                                        class="product-delete-button"
                                        title="{{ __('Delete Product') }}"
                                        aria-label="{{ __('Delete product') }}"
                                    >
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="product-empty-message">
                                    {{ __('No products found. Add your first product to get started.') }}
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
                <i class="bi bi-plus-circle product-button-icon"></i> {{ __('Add Product') }}
            </button>
            @if(count($products) > 0)
            <button
                id="erase-all-products-btn"
                class="products-erase-all-button"
                style="margin-left: 1rem; background: var(--color-danger); color: #fff; border-radius: 6px; padding: 0.5em 1.2em; border: none; font-weight: bold; cursor: pointer;"
            >
                <i class="bi bi-trash"></i> {{ __('Erase All') }}
            </button>
            @endif
        </div>
    


    <!-- Product Modal -->
    @if($showProductModal)
    <div class="product-modal-overlay">
        <div class="product-modal">
            <div class="product-modal-header">
                <h3 class="product-modal-title">
                    {{ $editMode ? __('Edit Product') : __('Add New Product') }}
                </h3>
            </div>
            <form wire:submit.prevent="saveProduct">
                <div class="product-modal-body">
                    <!-- Product Name -->
                    <div class="product-form-group">
                        <label for="name" class="product-form-label">
                            {{ __('Product Name') }}
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="product-form-input"
                            placeholder="{{ __('Enter product name') }}"
                        >
                        @error('name')
                            <span class="product-form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Product Price -->
                    <div class="product-form-group">
                        <label for="price" class="product-form-label">
                            {{ __('Price ($)') }}
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

                    <!-- IVA rate (SRI code); blank = venue default -->
                    <div class="product-form-group">
                        <label for="taxCode" class="product-form-label">
                            {{ __('IVA rate') }}
                        </label>
                        <select id="taxCode" wire:model="taxCode" class="product-form-input">
                            <option value="">{{ __('Venue default (:rate)', ['rate' => \App\Support\Tax::label(auth()->user()->venueSettingsUser()->defaultTaxCode())]) }}</option>
                            @foreach(\App\Support\Tax::catalogue() as $code => $row)
                                <option value="{{ $code }}">{{ $row['label'] }}</option>
                            @endforeach
                        </select>
                        @error('taxCode')
                            <span class="product-form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Category Selection -->
                    <div class="product-form-group">
                        <label for="category" class="product-form-label">
                            {{ __('Category') }}
                        </label>
                        <select
                            id="category"
                            wire:model="categoryId"
                            class="product-form-input"
                        >
                            <option value="">{{ __('Select a category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') 
                            <span class="product-form-error">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Icon Type Selection -->
                    <div class="product-form-group">
                        <label class="product-form-label">
                            {{ __('Icon Type') }}
                        </label>
                        <div class="product-icon-type-options">
                            <label class="product-icon-type-option">
                                <input
                                    type="radio"
                                    wire:model="iconType"
                                    value="bootstrap"
                                    class="product-icon-type-radio"
                                >
                                <span class="product-icon-type-label">{{ __('Bootstrap Icon') }}</span>
                            </label>
                            <label class="product-icon-type-option">
                                <input
                                    type="radio"
                                    wire:model="iconType"
                                    value="svg"
                                    class="product-icon-type-radio"
                                >
                                <span class="product-icon-type-label">{{ __('Upload Image') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Bootstrap Icon Selection -->
                    @if($iconType === 'bootstrap')
                    <div class="product-form-group">
                        <label for="bootstrapIcon" class="product-form-label">
                            {{ __('Bootstrap Icon Class') }}
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
                                {{ __('Enter a valid Bootstrap icon class (e.g., bi-cup, bi-cart, bi-egg-fried)') }}
                            </small>
                            <a href="https://icons.getbootstrap.com/" target="_blank" class="product-bootstrap-icon-link">
                                {{ __('Browse available icons on Bootstrap Icons website') }}
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
                            {{ __('Upload Icon Image') }}
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
                                <span class="product-visually-hidden">{{ __('Choose file') }}</span>
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
                                    <img src="{{ $svgFile->temporaryUrl() }}" alt="{{ __('Preview') }}" class="product-file-preview-image">
                                </div>
                            @elseif($iconValue)
                                <div class="product-file-preview">
                                    <img src="{{ asset('storage/' . $iconValue) }}" alt="{{ __('Current Icon') }}" class="product-file-preview-image">
                                    <span class="product-file-preview-label">{{ __('Current icon') }}</span>
                                </div>
                            @endif
                            <small class="product-file-upload-help">
                                {{ __('Upload JPG, PNG, SVG, or WebP (max 1MB). Square images work best.') }}
                            </small>
                            @error('svgFile') 
                                <span class="product-form-error">{{ $message }}</span> 
                            @enderror
                        </div>
                    </div>
                    @endif

                    <!-- Product Description -->
                    <div class="product-form-group">
                        <label for="description" class="product-form-label">
                            {{ __('Description') }}
                        </label>
                        <textarea
                            id="description"
                            wire:model.defer="description"
                            class="product-form-input"
                            placeholder="{{ __('Enter product description (optional)') }}"
                            rows="3"
                        ></textarea>
                        @error('description')
                            <span class="product-form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Product Photo Preview -->
                    @if($photoFile)
                        <div class="product-file-preview">
                            <img src="{{ $photoFile->temporaryUrl() }}" alt="{{ __('Preview') }}" class="product-file-preview-image">
                        </div>
                    @elseif($photo)
                        <div class="product-file-preview">
                            <img src="{{ asset('storage/' . $photo) }}" alt="{{ __('Current Photo') }}" class="product-file-preview-image">

                        </div>
                    @endif

                    <!-- Product Photo Upload -->
                    <div class="product-form-group">
                        <label for="photoFile" class="product-form-label">
                            {{ __('Product Photo') }}
                        </label>
                        <div x-data="{ isUploading: false, progress: 0 }"
                             x-on:livewire-upload-start="isUploading = true"
                             x-on:livewire-upload-finish="isUploading = false"
                             x-on:livewire-upload-error="isUploading = false"
                             x-on:livewire-upload-progress="progress = $event.detail.progress"
                             class="product-file-upload">
                            <label class="product-file-upload-label">
                                <span class="product-visually-hidden">{{ __('Choose file') }}</span>
                                <input
                                    type="file"
                                    id="photoFile"
                                    wire:model="photoFile"
                                    class="product-file-upload-input"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >
                            </label>
                            <div x-show="isUploading" class="product-upload-progress-container">
                                <div class="product-upload-progress-bar">
                                    <div class="product-upload-progress-value" x-bind:style="'width: ' + progress + '%'"/>
                                </div>
                            </div>
                            <small class="product-file-upload-help">
                                {{ __('Upload JPG, PNG, or WebP (max 1MB). Square images work best.') }}
                            </small>
                            @error('photoFile')
                                <span class="product-form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="product-modal-footer fixed-modal-footer">
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="product-cancel-button"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="product-submit-button"
                    >
                        {{ $editMode ? __('Update') : __('Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation - uses JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var eraseBtn = document.getElementById('erase-all-products-btn');
            if (eraseBtn) {
                eraseBtn.addEventListener('click', function() {
                    var msg = {{ Js::from(__('Are you sure you want to erase all products? This action cannot be undone.')) }};
                    if (confirm(msg)) {
                        @this.call('deleteAllConfirmed');
                    }
                });
            }
        });

        document.addEventListener('livewire:initialized', () => {
            @this.on('showDeleteConfirmation', (event) => {
                var msg = event.message || {{ Js::from(__('Are you sure you want to delete this product?')) }};
                if (confirm(msg)) {
                    @this.dispatch('deleteConfirmed');
                }
            });
        });
    </script>
</div>