<?php

use Illuminate\Support\Facades\Route;
Route::get('/images/{path}', function ($path) {
    $file = storage_path('app/public/' . $path);

    if (!is_file($file)) {
        abort(404);
    }

    return response()->download(
        $file,
        basename($file),
        [
            'Content-Type' => mime_content_type($file),
            'Content-Disposition' => 'inline',
        ]
    );
})->where('path', '.*');
Route::get('/test-image', function () {
    $path = storage_path('app/public/images/unknown_dish.webp');

    return response()->json([
        'path' => $path,
        'exists' => file_exists($path),
        'is_file' => is_file($path),
        'size' => file_exists($path) ? filesize($path) : null,
    ]);
});
use App\Http\Controllers\FilterController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\MicronutrientController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RationController;

Route::get('/filters', [FilterController::class, 'index']);
Route::get('/dishes', [DishController::class, 'index']);
Route::get('/dishes/{id}', [DishController::class, 'show']);
Route::get('/generator/filters', [GeneratorController::class, 'filters']);
Route::post('/generate-diet', [GeneratorController::class, 'generate']);
Route::post('/micronutrients/check', [MicronutrientController::class, 'check']);
Route::post('/calculate', [CalculatorController::class, 'calculate']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/dishes', [DishController::class, 'store']);
    Route::post('/dishes/{id}', [DishController::class, 'update']); // ← ДОБАВЛЕНО
    Route::put('/dishes/{id}', [DishController::class, 'update']);
    Route::delete('/dishes/{id}', [DishController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/rations', [RationController::class, 'index']);
    Route::get('/rations/{id}', [RationController::class, 'show']);
    Route::post('/rations', [RationController::class, 'store']);
    Route::put('/rations/{id}', [RationController::class, 'update']);
    Route::delete('/rations/{id}', [RationController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->get('/check-auth', function() {
    return response()->json([
        'user' => auth()->user(),
        'role' => auth()->user()?->role
    ]);
});