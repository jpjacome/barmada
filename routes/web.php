<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\GuestSessionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\AnalyticsPdfController;

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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// Tables Management Routes (accessible to both admins and editors).
// Only the index page exists — all table actions live in the Livewire
// component; the old create/show/edit resource leftovers are gone.
Route::resource('tables', TableController::class)->only(['index'])->middleware(['auth']);
Route::get('/tables/{table}/qr', [TableController::class, 'qrImage'])->name('tables.qr');

// Products route (accessible to both admins and editors)
Route::get('/products', [ProductsController::class, 'index'])->middleware(['auth'])->name('products.index');

// Orders: the live board is the single orders page.
Route::get('/orders/archive', [OrderController::class, 'archive'])->middleware(['auth', 'editor'])->name('orders.archive');
// Owner-only archive download. Filename is constrained to the editor's own
// naming pattern in the controller; the where() blocks path separators.
Route::get('/orders/archive/download/{filename}', [OrderController::class, 'downloadArchive'])
    ->middleware(['auth', 'editor'])
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('orders.archive.download');

// Editor/Admin order creation (NO ip.approved middleware)
Route::get('/order', [OrderController::class, 'orderEntry'])->middleware(['auth'])->name('orders.create');
Route::post('/order', [OrderController::class, 'store'])->middleware(['auth'])->name('orders.store');
Route::get('/order/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');

// Guest-accessible (QR/unique token) order flows. The informational
// waiting page is public; the order form, the session/bill page and all
// submissions are gated by device approval (ip.approved) and rate limited.
Route::get('/orders/waiting-approval', function () {
    return view('orders.waiting-approval');
})->name('orders.waiting-approval');
Route::post('/order/{unique_token}', [TableController::class, 'storeGuestOrder'])
    ->withoutMiddleware(['web'])
    ->middleware(['ip.approved', 'throttle:60,1'])
    ->name('order.guest.store');

// Guest "my table" page: orders this session + running bill.
Route::get('/order/{unique_token}/session', [GuestSessionController::class, 'show'])
    ->middleware(['ip.approved', 'throttle:60,1'])
    ->name('order.session');
// Guest service signals: request the bill / call a waiter.
Route::post('/order/{unique_token}/service', [GuestSessionController::class, 'requestService'])
    ->withoutMiddleware(['web'])
    ->middleware(['ip.approved', 'throttle:10,1'])
    ->name('order.service');

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
    Route::post('/admin/impersonate/{id}', [ImpersonateController::class, 'impersonate'])->whereNumber('id')->name('admin.impersonate');
    // Delete an establishment and all of its data.
    Route::delete('/admin/editors/{id}', [EditorController::class, 'destroy'])->whereNumber('id')->name('admin.editors.destroy');
});

// Leaving impersonation must be reachable while authenticated as the
// impersonated (non-admin) editor; the controller verifies an active
// impersonation session before restoring the admin account.
Route::post('/admin/impersonate/leave', [ImpersonateController::class, 'leave'])
    ->middleware(['auth'])
    ->name('admin.impersonate.leave');

// Staff placeholder page (for editors)
Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('/staff', function () {
        return view('staff.index');
    })->name('staff.index');
});

// Analytics dashboard route for editors
Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('/analytics', \App\Livewire\AnalyticsDashboard::class)->name('analytics.dashboard');
    Route::get('/analytics/pdf', [AnalyticsPdfController::class, 'export'])->name('analytics.pdf.export');
    Route::post('/analytics/pdf-with-charts', [AnalyticsPdfController::class, 'exportWithCharts'])->name('analytics.pdf.export.withcharts');
    Route::get('/analytics/csv', [AnalyticsPdfController::class, 'exportCsv'])->name('analytics.csv.export');
});

// Order form via unique token: device approval enforced
Route::get('/order/{unique_token}', [TableController::class, 'redirectToOrder'])
    ->middleware(['ip.approved'])
    ->name('order.redirect');

// QR Entry route for customers scanning the QR code at the table
// (creates approval requests — rate limited)
Route::get('/qr-entry/{editorname}/{table_number}', [OrderController::class, 'qrEntry'])
    ->middleware(['throttle:30,1'])
    ->name('orders.qr-entry');

// Polling endpoint for table status (rate limited)
Route::get('/poll-table-status/{table}', [OrderController::class, 'pollTableStatus'])
    ->middleware(['throttle:120,1']);

// Auth routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/logo', [SettingsController::class, 'updateLogo'])->name('settings.update-logo');
    Route::post('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.update-business');
});

// Make theme toggle available to all (guests and users)
Route::post('/settings/toggle-theme', [SettingsController::class, 'toggleTheme'])->name('settings.toggle-theme');

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
