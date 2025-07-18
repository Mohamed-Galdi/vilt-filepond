<?php

use Illuminate\Support\Facades\Route;
use MohamedGaldi\ViltFilepond\Http\Controllers\FilePondController;

Route::group([
    'prefix' => config('vilt-filepond.routes.prefix'),
    'middleware' => config('vilt-filepond.routes.middleware'),
], function () {
    Route::post('/upload', [FilePondController::class, 'upload'])->name('filepond.upload');
    Route::patch('/patch', [FilePondController::class, 'patch'])->name('filepond.patch');
    Route::match(['HEAD', 'GET'], '/restore', [FilePondController::class, 'restore'])->name('filepond.restore');
    Route::delete('/revert/{folder}', [FilePondController::class, 'revert'])->name('filepond.revert');
    Route::get('/load/{fileId}', [FilePondController::class, 'load'])->name('filepond.load');
});
