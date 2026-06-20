<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CameraController;
use App\Http\Controllers\Api\V1\EdgeConfigController;
use App\Http\Controllers\Api\V1\FcmTokenController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LocationManagementController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Middleware\AuthenticateEdgeDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/metrics/prometheus', [MetricsController::class, 'prometheus']);

    Route::get('/edge/{deviceUid}/config', [EdgeConfigController::class, 'show'])
        ->middleware(AuthenticateEdgeDevice::class)
        ->where('deviceUid', '[a-z0-9-]+');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/users/me/fcm-token', [FcmTokenController::class, 'store']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::get('/locations/{locationId}', [LocationManagementController::class, 'show'])
            ->whereNumber('locationId');
        Route::patch('/locations/{locationId}', [LocationManagementController::class, 'update'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/state', [LocationController::class, 'state'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/events', [LocationController::class, 'events'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/cutoff-events', [LocationManagementController::class, 'cutoffEvents'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/cutoff-accuracy', [LocationManagementController::class, 'cutoffAccuracy'])
            ->whereNumber('locationId');
        Route::post('/locations/{locationId}/cutoff/override', [LocationManagementController::class, 'override'])
            ->whereNumber('locationId');
        Route::get('/locations/{locationId}/announcements', [LocationManagementController::class, 'announcements'])
            ->whereNumber('locationId');

        Route::get('/locations/{locationId}/cameras', [CameraController::class, 'index'])
            ->whereNumber('locationId');
        Route::patch('/locations/{locationId}/cameras/{cameraId}', [CameraController::class, 'update'])
            ->whereNumber('locationId')
            ->whereNumber('cameraId');

        Route::get('/cross-counter/recommendations', [LocationManagementController::class, 'crossCounterRecommendations']);
    });
});
