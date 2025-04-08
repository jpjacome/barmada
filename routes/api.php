<?php
// This file intentionally left minimal to avoid display issues
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Number;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Get new numbers since a given ID
Route::get('/numbers', function (Request $request) {
    $afterId = (int)$request->query('after', 0);
    
    // Log the request for debugging
    \Log::info("API request for numbers after ID: {$afterId}");
    
    $numbers = Number::where('id', '>', $afterId)
                    ->orderBy('id', 'desc')
                    ->get();
    
    // Format the response
    $result = [
        'numbers' => $numbers,
        'count' => $numbers->count(),
        'timestamp' => now()->toIso8601String(),
        'requested_after_id' => $afterId
    ];
    
    \Log::info("Returning {$numbers->count()} numbers", ['first_id' => $numbers->first()?->id]);
    
    return response()->json($result);
}); 