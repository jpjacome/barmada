<div class="categories-panel">
    <h2>Manage Categories</h2>
    <!-- Add Category Form -->
    <form wire:submit.prevent="addCategory" class="add-category-form">
        <input type="text" wire:model.defer="newCategoryName" placeholder="Add new category" required>
        <button type="submit" class="btn btn-primary">Add</button>
        @error('newCategoryName')
            <span class="error-text">{{ $message }}</span>
        @enderror
    </form>
    @if($status)
        <div class="status-message">{{ $status }}</div>
    @endif
    <p class="info-text">The position of categories here determines the order in which products are displayed in the create order menu.</p>
    <!-- Categories List -->
    <ul class="categories-list">
        @foreach ($categories as $category)
            <li class="category-item">
                <span>{{ $category->name }}</span>
                <div class="category-actions">
                    <button wire:click="moveUp({{ $category->id }})" class="btn btn-primary" title="Move Up"><i class="bi bi-arrow-up"></i></button>
                    <button wire:click="moveDown({{ $category->id }})" class="btn btn-secondary" title="Move Down"><i class="bi bi-arrow-down"></i></button>
                    <button wire:click="deleteCategory({{ $category->id }})" class="btn btn-danger" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
            </li>
        @endforeach
    </ul>
</div>
