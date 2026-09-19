<?php

use App\Http\Controllers\Api\V1\FixtureController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/fixtures', [FixtureController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('api.v1.fixtures.index');

    Route::get('/fixtures/{fixture}', [FixtureController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('api.v1.fixtures.show');
});
