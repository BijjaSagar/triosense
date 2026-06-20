<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::get('/locations/{locationId}/state', [LocationController::class, 'state'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/events', [LocationController::class, 'events'])
            ->whereNumber('locationId');
    });
});
