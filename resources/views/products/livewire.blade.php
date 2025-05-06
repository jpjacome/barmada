@extends('layouts.app')

@section('content')
<!-- Link to general CSS first, then component-specific CSS -->
<link href="{{ asset('css/general-' . (session('theme', 'light')) . '.css') }}" rel="stylesheet">
<div class="page-container">
    <div class="page-content">
        <div class="content-card">
            <div class="content-card-body">
                @livewire('categories-panel')
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