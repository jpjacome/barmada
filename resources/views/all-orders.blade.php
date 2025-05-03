@extends('layouts.app')
@section('header')
    <h1 class="page-title">Orders</h1>
@endsection
@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:all-orders-list />
        </div>
    </div>
@endsection