@extends('layouts.app')

@section('title', __('Inventory') . ' · Barmada')

@section('content')
<link href="{{ asset('css/general-' . (session('theme', 'light')) . '.css') }}" rel="stylesheet">
<link href="{{ asset('css/products-list.css') }}" rel="stylesheet">
<div class="page-container">
    <div class="page-content">
        <div class="content-card">
            <div class="content-card-body">
                @livewire('inventory-panel')
            </div>
        </div>
    </div>
</div>
@endsection
