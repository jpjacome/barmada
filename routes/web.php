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

// Admin-only routes
Route::middleware(['auth', 'admin'])->group(function () {
    // Tables Management Routes
    Route::resource('tables', TableController::class);
    
    // Products route (renamed from numbers/livewire)
    Route::get('/products', [NumberController::class, 'livewire'])->name('products.index');

    // Orders routes
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    
    // Orders Archive route
    Route::get('/orders/archive', [OrderController::class, 'archive'])->name('orders.archive');
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
Route::get('/order', [OrderController::class, 'create'])->name('orders.create');
Route::post('/order', [OrderController::class, 'store'])->name('orders.store');
Route::get('/order/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');

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

// Category Management Routes
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
Route::post('/categories/{category}/move-up', [CategoryController::class, 'moveUp'])->name('categories.move-up');
Route::post('/categories/{category}/move-down', [CategoryController::class, 'moveDown'])->name('categories.move-down');

require __DIR__.'/auth.php';
