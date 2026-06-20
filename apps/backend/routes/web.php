<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Broadcast::routes(['middleware' => ['auth:sanctum']]);
