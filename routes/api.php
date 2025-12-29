<?php

use App\Http\Controllers\Api\VideoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

Route::prefix('v1')->group(function () {
    // Video transcoding routes
    Route::prefix('videos')->group(function () {
        Route::post('/', [VideoController::class, 'upload'])->name('videos.upload');
        Route::get('/', [VideoController::class, 'index'])->name('videos.index');
        // HLS file serving route (must come before /{uuid} to avoid route conflicts)
        Route::get('/{uuid}/hls/{filename}', [VideoController::class, 'serveHls'])->name('videos.hls');
        Route::get('/{uuid}', [VideoController::class, 'show'])->name('videos.show');
        Route::delete('/{uuid}', [VideoController::class, 'destroy'])->name('videos.destroy');
        Route::post('/{uuid}/retry', [VideoController::class, 'retry'])->name('videos.retry');
    });

    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('health');
});

