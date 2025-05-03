<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NumberController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Number;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Temporary admin password reset route
Route::get('/reset-admin', function () {
    $user = User::find(1); // Get user with ID 1
    
    if ($user) {
        $newPassword = 'password123';
        $user->password = Hash::make($newPassword);
        $user->save();
        return "Admin password reset to: " . $newPassword;
    }
    
    return "Admin user not found!";
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// Tables Management Routes (accessible to both admins and editors)
Route::resource('tables', TableController::class)->middleware(['auth']);
Route::get('/tables/{table}/qr', [\App\Http\Controllers\TableController::class, 'qrImage'])->name('tables.qr');

// Products route (accessible to both admins and editors)
Route::get('/products', [NumberController::class, 'livewire'])->middleware(['auth'])->name('products.index');

// Orders routes (accessible to both admins and editors)
Route::get('/orders', [OrderController::class, 'index'])->middleware(['auth'])->name('orders.index');
Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware(['auth'])->name('orders.update');
Route::get('/orders/archive', [OrderController::class, 'archive'])->middleware(['auth'])->name('orders.archive');

// Admin-only routes
Route::middleware(['auth', 'admin'])->group(function () {
    // Admin dashboard route
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');
    Route::get('/admin/editors', function () {
        $editors = User::where('is_editor', true)->paginate(10);
        return view('admin.editors', compact('editors'));
    })->name('admin.editors');
    Route::post('/admin/impersonate/{id}', [ImpersonateController::class, 'impersonate'])->name('admin.impersonate');
    Route::post('/admin/impersonate/leave', [ImpersonateController::class, 'leave'])->name('admin.impersonate.leave');
});

// Staff placeholder page (for editors)
Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('/staff', function () {
        return view('staff.index');
    })->name('staff.index');
});

// Analytics dashboard route for editors
Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('/analytics', \App\Livewire\AnalyticsDashboard::class)->name('analytics.dashboard');
});

// Remove unused Number routes
// Redirect old routes to avoid errors
Route::get('/numbers', function() {
    return redirect()->route('dashboard');
})->name('numbers.index');

Route::get('/numbers/create', function() {
    return redirect()->route('dashboard');
})->name('numbers.create');

Route::get('/numbers/live', function() {
    return redirect()->route('dashboard');
})->name('numbers.live');

Route::get('/numbers/livewire', function() {
    return redirect()->route('products.index');
})->name('numbers.livewire');

// Guest-accessible routes
Route::get('/order', [OrderController::class, 'orderEntry'])->middleware(['auth', 'admin'])->name('orders.create');
Route::post('/order', [OrderController::class, 'store'])->name('orders.store');
Route::get('/order/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');

// Add a route to handle redirection based on the unique token
Route::get('/order/{unique_token}', [TableController::class, 'redirectToOrder'])->name('order.redirect');

// QR Entry route for customers scanning the QR code at the table
Route::get('/qr-entry/{editorname}/{table_number}', [OrderController::class, 'qrEntry'])->name('orders.qr-entry');

// Polling endpoint for table status
Route::get('/poll-table-status/{table}', [OrderController::class, 'pollTableStatus']);

// API route for fetching order data
Route::get('/api/orders/{order}', [OrderController::class, 'getOrderData']);
Route::put('/orders/{order}', [OrderController::class, 'updateOrder'])->name('orders.updateAjax');

// Auth routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/logo', [SettingsController::class, 'updateLogo'])->name('settings.update-logo');
    Route::post('/settings/toggle-theme', [SettingsController::class, 'toggleTheme'])->name('settings.toggle-theme');
});

// API proxy for numbers - kept for numbers creation functionality
Route::get('/api-numbers', function (Request $request) {
    $afterId = (int)$request->query('after', 0);
    
    \Log::info("Web API proxy: numbers after ID: {$afterId}");
    
    $numbers = Number::where('id', '>', $afterId)
                    ->orderBy('id', 'desc')
                    ->get();
    
    $result = [
        'numbers' => $numbers,
        'count' => $numbers->count(),
        'timestamp' => now()->toIso8601String(),
        'requested_after_id' => $afterId
    ];
    
    \Log::info("Web API proxy: Returning {$numbers->count()} numbers");
    
    return response()->json($result);
});

Route::get('/all-orders', function () {
    return view('all-orders');
})->middleware('auth')   // ✨ only logged‑in users
  ->name('all-orders');

// Category Management Routes (accessible to both admins and editors)
Route::post('/categories', [CategoryController::class, 'store'])->middleware(['auth'])->name('categories.store');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware(['auth'])->name('categories.destroy');
Route::post('/categories/{category}/move-up', [CategoryController::class, 'moveUp'])->middleware(['auth'])->name('categories.move-up');
Route::post('/categories/{category}/move-down', [CategoryController::class, 'moveDown'])->middleware(['auth'])->name('categories.move-down');

require __DIR__.'/auth.php';
