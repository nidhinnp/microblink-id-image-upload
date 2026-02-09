<?php

use Illuminate\Support\Facades\Route;
use Microblink\IdImageUpload\Http\Controllers\ImageUploadController;

/*
|--------------------------------------------------------------------------
| Microblink Image Upload API Routes
|--------------------------------------------------------------------------
|
| These routes handle ID/document image uploads to the Microblink API.
| You can customize these routes by publishing them to your application.
|
*/

Route::prefix('api/microblink')
    ->middleware(['api'])
    ->group(function () {
        // Single image upload (file)
        Route::post('/image-upload', [ImageUploadController::class, 'upload'])
            ->name('microblink.upload');

        // Multi-side image upload (front & back files)
        Route::post('/image-upload/multi-side', [ImageUploadController::class, 'uploadMultiSide'])
            ->name('microblink.upload.multi-side');

        // Base64 image upload
        Route::post('/image-upload/base64', [ImageUploadController::class, 'uploadBase64'])
            ->name('microblink.upload.base64');

        // Multi-side base64 upload
        Route::post('/image-upload/multi-side/base64', [ImageUploadController::class, 'uploadMultiSideBase64'])
            ->name('microblink.upload.multi-side.base64');
    });
