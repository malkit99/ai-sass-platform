<?php

use App\Http\Controllers\Api\Media\MediaFileController;
use Illuminate\Support\Facades\Route;

// Loaded via Route::prefix('media')->group(...) in routes/api.php — every
// path here is already under /api/media/*.

Route::middleware(['auth:sanctum', 'trial.active'])->group(function () {
    Route::get('/usage', [MediaFileController::class, 'usage']);
    Route::get('/', [MediaFileController::class, 'index']);
    Route::post('/', [MediaFileController::class, 'store']);
    Route::delete('/{file}', [MediaFileController::class, 'destroy']);
});
