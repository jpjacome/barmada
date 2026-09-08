@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
<div class="dashboard-wrapper">
    <div class="dashboard-main">
        @if (session('error'))
            <div class="alert alert-danger" role="alert">
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @php $user = Auth::user(); @endphp
        <div class="dashboard-container">
            <div class="dashboard-header">
                @php
                    // Staff belong to a venue; editors are one. Every figure on
                    // this page is that venue's, never the staff user's own id.
                    $tenantId = $user->effectiveEditorId();
                    $venue = $user->is_editor ? $user : ($tenantId ? \App\Models\User::withoutGlobalScopes()->find($tenantId) : null);
                @endphp
                <h1 class="dashboard-title">{{ __('Hello, :username', ['username' => $user->first_name ?: $user->name ?: $user->username]) }}</h1>
                @if($venue && $venue->business_name)
                    <p class="dashboard-subtitle">{{ $venue->business_name }}</p>
                @endif
            </div>
            <!-- Recent Activity Section -->
            <div class="recent-activity">
                <h3 class="recent-activity-title">
                    <i class="bi bi-activity recent-activity-icon"></i>
                    {{ __('Recent Activity') }}
                </h3>
                <ul class="activity-list">
                    @php
                        // EditorScope already bounds these to the caller's tenant
                        // (staff included) and lets admins see everything.
                        $orderActivities = App\Models\Order::with('table')->latest()->take(3)->get();
                        $paymentActivities = App\Models\ActivityLog::latest()->take(3)->get();
                        $allActivities = collect($orderActivities->map(function($order) {
                            return [
                                'type' => 'order',
                                // The venue's table number, not the database id.
                                'description' => $order->table
                                    ? __('New order #:id for Table :table', ['id' => $order->id, 'table' => $order->table->table_number])
                                    : __('New order #:id', ['id' => $order->id]),
                                'created_at' => $order->created_at
                            ];
                        })->concat($paymentActivities->map(function($activity) {
                            return [
                                'type' => $activity->type,
                                'description' => $activity->description,
                                'created_at' => $activity->created_at
                            ];
                        })))->sortByDesc('created_at')->take(5);
                    @endphp
                    @if($allActivities->isEmpty())
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title">{{ __('No recent activity') }}</div>
                            </div>
                        </li>
                    @else
                        @foreach($allActivities as $activity)
                            <li class="activity-item">
                                <div class="activity-dot activity-dot-{{ $activity['type'] }}"></div>
                                <div class="activity-content">
                                    <div class="activity-title">{{ $activity['description'] }}</div>
                                    <div class="activity-time">{{ $activity['created_at']->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    @endif
                </ul>
            </div>
            <!-- Combined Management Cards -->
            <div class="dashboard-cards">
                <div class="action-card">
                    <div class="action-card-header">
                        <div class="action-card-icon-container tables-icon-bg">
                            <i class="bi bi-table stat-card-icon"></i>
                        </div>
                        <div class="action-card-title-container">
                            <h3 class="action-card-title">{{ __('Table Management') }}</h3>
                            <p class="action-card-subtitle">{{ __('Organize your venue layout') }}</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ App\Models\Table::whereNull('archived_at')->count() }}
                            <p class="stat-card-description">{{ __('Active tables in your venue') }}</p>
                        </div>
                        <p class="action-card-description">
                            {{ __('Set up and manage tables in your venue. View table status, add new tables, and monitor orders per table.') }}
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('tables.index') }}" class="btn btn-primary">
                            <i class="bi bi-table btn-icon"></i> {{ __('Manage Tables') }}
                        </a>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-card-header">
                        <div class="action-card-icon-container products-icon-bg">
                            <i class="bi bi-box stat-card-icon"></i>
                        </div>
                        <div class="action-card-title-container">
                            <h3 class="action-card-title">{{ __('Product Catalog') }}</h3>
                            <p class="action-card-subtitle">{{ __('Manage your menu items') }}</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ App\Models\Product::count() }}
                            <p class="stat-card-description">{{ __('Products in your catalog') }}</p>
                        </div>
                        <p class="action-card-description">
                            {{ __('Manage your product catalog with custom icons and organized categories. Update prices and availability.') }}
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('products.index') }}" class="btn btn-primary">
                            <i class="bi bi-box-fill btn-icon"></i> {{ __('Manage Products') }}
                        </a>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-card-header">
                        <div class="action-card-icon-container orders-icon-bg">
                            <i class="bi bi-cart stat-card-icon"></i>
                        </div>
                        <div class="action-card-title-container">
                            <h3 class="action-card-title">{{ __('Order Management') }}</h3>
                            <p class="action-card-subtitle">{{ __('Track and process orders') }}</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ App\Models\Order::count() }}
                            <p class="stat-card-description">{{ __('Total orders processed') }}</p>
                        </div>
                        <p class="action-card-description">
                            {{ __('Create new orders, monitor pending orders in real-time, and keep track of order history. Export orders to XML for backup.') }}
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('orders.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle btn-icon"></i> {{ __('New Order') }}
                        </a>
                        <a href="{{ route('all-orders') }}" class="btn btn-outline btn-orders">
                            <i class="bi bi-list-ul btn-icon"></i> {{ __('View Orders') }}
                        </a>
                        @if($user->is_editor || $user->is_admin)
                        {{-- Archives are owner-only (the route 403s for staff). --}}
                        <a href="{{ route('orders.archive') }}" class="btn btn-outline btn-archive">
                            <i class="bi bi-archive btn-icon"></i> {{ __('Archives') }}
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            <!-- App Info -->
            <div class="dashboard-footer">
                <h4 class="dashboard-footer-subtitle">{{ __('Barmada Bar Management Dashboard') }}</h4>
                <p class="dashboard-footer-text">
                    {{ __('Version 1.0') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
