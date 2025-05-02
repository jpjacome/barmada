@extends('layouts.app')

@section('content')
<!-- Link to general CSS first, then component-specific CSS -->
<link href="{{ asset('css/general-' . (session('theme', 'light')) . '.css') }}" rel="stylesheet">
<div class="page-container">
    <div class="page-content">
        <div class="content-card">
            <div class="content-card-body">
                <h2>Manage Categories</h2> 
                <!-- Add Category Form -->
                <form method="POST" action="{{ route('categories.store') }}" class="add-category-form">
                    @csrf
                    <input type="text" name="name" placeholder="Add new category" required>
                    <button type="submit" class="btn btn-primary">Add</button>
                </form>
                <p class="info-text">The position of categories here determines the order in which products are displayed in the create order menu.</p>
                <!-- Categories List -->
                <ul class="categories-list">
                    @foreach ($categories->sortBy('sort_order') as $category)
                        <li class="category-item">
                            <span>{{ $category->name }}</span>
                            <div class="category-actions">
                                <!-- Move Up -->
                                <form method="POST" action="{{ route('categories.move-up', $category->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-arrow-up"></i>
                                    </button>
                                </form>
                                <!-- Move Down -->
                                <form method="POST" action="{{ route('categories.move-down', $category->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="bi bi-arrow-down"></i>
                                    </button>
                                </form>
                                <!-- Delete -->
                                <form method="POST" action="{{ route('categories.destroy', $category->id) }}" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="content-card">
            <div class="content-card-body">
                @livewire('products-list')
            </div>
        </div>
    </div>
</div>
@endsection