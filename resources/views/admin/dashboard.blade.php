@extends('layouts.app')

@section('content')
<div class="admin-dashboard-container">
    <div class="admin-dashboard-card">
        <h1 class="admin-dashboard-title">Welcome, Admin</h1>
        <p class="admin-dashboard-text">This is your admin dashboard. Here you can manage editor accounts and view system status.</p>
        <a href="{{ route('admin.editors') }}" class="admin-dashboard-link">Manage Editors</a>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
@endpush