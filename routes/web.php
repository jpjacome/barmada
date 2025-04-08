<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NumberController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Number;

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

// Tables Management Routes
Route::middleware(['auth'])->group(function () {
    Route::resource('tables', TableController::class);
});

// Number routes
Route::get('/numbers', [NumberController::class, 'index'])->name('numbers.index');
Route::get('/numbers/create', [NumberController::class, 'create'])->name('numbers.create');
Route::post('/numbers', [NumberController::class, 'store'])->name('numbers.store');
Route::get('/numbers/live', [NumberController::class, 'live'])->name('numbers.live');
Route::get('/numbers/livewire', [NumberController::class, 'livewire'])->name('numbers.livewire');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// API proxy for numbers
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

require __DIR__.'/auth.php';
