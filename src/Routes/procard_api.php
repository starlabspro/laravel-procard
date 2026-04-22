<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Starlabs\LaravelProcard\Http\Controllers\ProcardController;

Route::name('procard.')->group(function () {
    Route::post('procard/callback', [ProcardController::class, 'callback'])
        ->name('callback');

    Route::post('procard/initiate', [ProcardController::class, 'initiate'])
        ->name('initiate');

    Route::get('procard/approve', [ProcardController::class, 'approve'])
        ->name('approve');

    Route::get('procard/decline', [ProcardController::class, 'decline'])
        ->name('decline');

    Route::get('procard/cancel', [ProcardController::class, 'cancel'])
        ->name('cancel');
});
