<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EdgeDevice;
use App\Services\Edge\EdgeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EdgeConfigController extends Controller
{
    public function __construct(
        private readonly EdgeConfigService $edgeConfig,
    ) {
    }

    public function show(Request $request, string $deviceUid): JsonResponse
    {
        /** @var EdgeDevice|null $device */
        $device = $request->attributes->get('edge_device');

        if (! $device instanceof EdgeDevice) {
            Log::error('edge config missing authenticated device', ['device_uid' => $deviceUid]);

            return $this->errorResponse('edge_unauthorized', 'Edge device authentication failed.', status: 401);
        }

        return $this->successResponse($this->edgeConfig->buildConfigPayload($device));
    }
}
