<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\EdgeDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateEdgeDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUid = (string) $request->route('deviceUid', '');
        $apiKey = (string) $request->header('X-Edge-Api-Key', '');

        if ($deviceUid === '' || $apiKey === '') {
            Log::warning('edge auth missing credentials', ['device_uid' => $deviceUid]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'edge_unauthorized',
                    'message' => 'Edge device API key required.',
                    'details' => null,
                ],
                'meta' => [
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'timestamp' => now()->utc()->toIso8601String(),
                ],
            ], 401);
        }

        $device = EdgeDevice::query()
            ->withoutGlobalScopes()
            ->where('device_uid', $deviceUid)
            ->first();

        if ($device === null || $device->api_key_hash === null) {
            Log::warning('edge auth unknown device', ['device_uid' => $deviceUid]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'edge_not_found',
                    'message' => 'Edge device not found or not provisioned.',
                    'details' => null,
                ],
                'meta' => [
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'timestamp' => now()->utc()->toIso8601String(),
                ],
            ], 404);
        }

        if (! Hash::check($apiKey, (string) $device->api_key_hash)) {
            Log::warning('edge auth invalid key', ['device_uid' => $deviceUid]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'edge_unauthorized',
                    'message' => 'Invalid edge device API key.',
                    'details' => null,
                ],
                'meta' => [
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'timestamp' => now()->utc()->toIso8601String(),
                ],
            ], 401);
        }

        $request->attributes->set('edge_device', $device);

        return $next($request);
    }
}
