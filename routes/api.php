<?php

use App\Http\Controllers\TranscodeController;
use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.project')->group(function () {
    Route::post('/transcode', [TranscodeController::class, 'create']);
    Route::get('/transcode/{projectKey}/{videoId}/status', [TranscodeController::class, 'status']);
    Route::get('/download/{projectKey}/{videoId}/{quality}', [DownloadController::class, 'download']);
});
