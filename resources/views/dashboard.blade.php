@extends('layouts.app')

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
                <h1 class="dashboard-title">Hello, {{ $user->username }}</h1>
            </div>
            <!-- Recent Activity Section -->
            <div class="recent-activity">
                <h3 class="recent-activity-title">
                    <i class="bi bi-activity recent-activity-icon"></i>
                    Recent Activity
                </h3>
                <ul class="activity-list">
                    @php
                        $orderActivities = $user->is_admin
                            ? App\Models\Order::latest()->take(3)->get()
                            : App\Models\Order::where('editor_id', $user->id)->latest()->take(3)->get();
                        $paymentActivities = $user->is_admin
                            ? App\Models\ActivityLog::latest()->take(3)->get()
                            : App\Models\ActivityLog::where('editor_id', $user->id)->latest()->take(3)->get();
                        $allActivities = collect($orderActivities->map(function($order) {
                            return [
                                'type' => 'order',
                                'description' => $order->table ? "New order #{$order->id} for Table {$order->table->id}" : "New order #{$order->id}",
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
                                <div class="activity-title">No recent activity</div>
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
                            <h3 class="action-card-title">Table Management</h3>
                            <p class="action-card-subtitle">Organize your venue layout</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ $user->is_admin ? App\Models\Table::count() : App\Models\Table::where('editor_id', $user->id)->count() }}
                            <p class="stat-card-description">Active tables in your venue</p>
                        </div>
                        <p class="action-card-description">
                            Set up and manage tables in your venue. View table status, add new tables, and monitor orders per table.
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('tables.index') }}" class="btn btn-primary">
                            <i class="bi bi-table btn-icon"></i> Manage Tables
                        </a>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-card-header">
                        <div class="action-card-icon-container products-icon-bg">
                            <i class="bi bi-box stat-card-icon"></i>
                        </div>
                        <div class="action-card-title-container">
                            <h3 class="action-card-title">Product Catalog</h3>
                            <p class="action-card-subtitle">Manage your menu items</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ $user->is_admin ? App\Models\Product::count() : App\Models\Product::where('editor_id', $user->id)->count() }}
                            <p class="stat-card-description">Products in your catalog</p>
                        </div>
                        <p class="action-card-description">
                            Manage your product catalog with custom icons and organized categories. Update prices and availability.
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('products.index') }}" class="btn btn-primary">
                            <i class="bi bi-box-fill btn-icon"></i> Manage Products
                        </a>
                    </div>
                </div>
                <div class="action-card">
                    <div class="action-card-header">
                        <div class="action-card-icon-container orders-icon-bg">
                            <i class="bi bi-cart stat-card-icon"></i>
                        </div>
                        <div class="action-card-title-container">
                            <h3 class="action-card-title">Order Management</h3>
                            <p class="action-card-subtitle">Track and process orders</p>
                        </div>
                    </div>
                    <div class="action-card-body">
                        <div class="stat-card-value">{{ $user->is_admin ? App\Models\Order::count() : App\Models\Order::where('editor_id', $user->id)->count() }}
                            <p class="stat-card-description">Total orders processed</p>
                        </div>
                        <p class="action-card-description">
                            Create new orders, monitor pending orders in real-time, and keep track of order history. Export orders to XML for backup.
                        </p>
                    </div>
                    <div class="action-card-footer">
                        <a href="{{ route('orders.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle btn-icon"></i> New Order
                        </a>
                        <a href="{{ route('orders.index') }}" class="btn btn-outline btn-orders">
                            <i class="bi bi-list-ul btn-icon"></i> View Orders
                        </a>
                        <a href="{{ route('orders.archive') }}" class="btn btn-outline btn-archive">
                            <i class="bi bi-archive btn-icon"></i> Archives
                        </a>
                    </div>
                </div>
            </div>
            <!-- App Info -->
            <div class="dashboard-footer">
                <h4 class="dashboard-footer-subtitle">Barmada Bar Management Dashboard</h4>
                <p class="dashboard-footer-text">
                    Version 1.0
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
