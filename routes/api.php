<?php

use App\Http\Controllers\Api\V1\ApprovalRequestController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — the staff mobile app's surface (Flutter Phase 0)
|--------------------------------------------------------------------------
|
| Token auth via Sanctum. Authorization rides on the SAME policies and the
| EditorScope tenancy as the web app: implicit model binding resolves
| through the global scope, so cross-tenant ids 404 for editors and staff,
| and every mutation re-checks its policy. Domain rules surface as 422s
| (see bootstrap/app.php).
|
| This PR ships the live-service surface (running a bar night). Catalog
| management, staff accounts, settings, analytics and platform-admin
| endpoints arrive in the next PR of the series.
*/

Route::prefix('v1')->group(function () {
    // Public: server discovery for the app's add-server screen, and login.
    Route::get('/meta', MetaController::class)->middleware('throttle:30,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'throttle:240,1'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);

        // Push-notification device registry (delivery lands in a later PR).
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::delete('/devices/{device_uuid}', [DeviceController::class, 'destroy']);

        // The live board: one payload per poll — pending orders, approval
        // requests and service requests together.
        Route::get('/board', BoardController::class);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
        Route::post('/orders/{order}/items/toggle-paid', [OrderController::class, 'toggleItemPaid']);
        Route::post('/orders/{order}/settle', [OrderController::class, 'settle']);

        Route::get('/tables', [TableController::class, 'index']);
        Route::get('/tables/{table}/session', [TableController::class, 'session']);
        Route::post('/tables/{table}/open', [TableController::class, 'open']);
        Route::post('/tables/{table}/close', [TableController::class, 'close']);
        Route::post('/tables/{table}/approve', [TableController::class, 'approve']);
        Route::post('/tables/{table}/settle', [TableController::class, 'settle']);
        Route::post('/tables/{table}/archive', [TableController::class, 'archive']);
        Route::post('/tables/{table}/restore', [TableController::class, 'restore']);
        Route::post('/tables/{table}/invoice', [TableController::class, 'saveInvoice']);

        Route::get('/approval-requests', [ApprovalRequestController::class, 'index']);
        Route::post('/approval-requests/{id}/approve', [ApprovalRequestController::class, 'approve'])
            ->whereNumber('id');

        Route::get('/service-requests', [ServiceRequestController::class, 'index']);
        Route::post('/service-requests/{serviceRequest}/done', [ServiceRequestController::class, 'done']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products/{product}/toggle-availability', [ProductController::class, 'toggleAvailability']);
    });
});
