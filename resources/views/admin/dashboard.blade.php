@extends('layouts.app')

@section('title', __('Admin Dashboard'))

@section('content')
<div class="admin-dashboard-container">
    <div class="admin-dashboard-card">
        <h1 class="admin-dashboard-title">{{ __('Welcome, Admin') }}</h1>
        <p class="admin-dashboard-text">{{ __('This is your admin dashboard. Here you can manage editor accounts and view system status.') }}</p>
        <a href="{{ route('admin.editors') }}" class="admin-dashboard-link">{{ __('Manage Editors') }}</a>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
@endpush