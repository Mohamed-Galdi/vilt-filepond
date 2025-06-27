<?php

use Illuminate\Support\Facades\Route;
use MohamedGaldi\ViltFilepond\Http\Controllers\FilePondController;

Route::group([
    'prefix' => config('filepond.routes.prefix'),
    'middleware' => config('filepond.routes.middleware'),
], function () {
    Route::post('/upload', [FilePondController::class, 'upload'])->name('filepond.upload');
    Route::delete('/revert/{folder}', [FilePondController::class, 'revert'])->name('filepond.revert');
    Route::get('/load/{fileId}', [FilePondController::class, 'load'])->name('filepond.load');
});
