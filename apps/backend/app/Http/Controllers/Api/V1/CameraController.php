<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCameraRequest;
use App\Models\Camera;
use App\Models\Location;
use App\Services\Cameras\CameraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class CameraController extends Controller
{
    public function __construct(
        private readonly CameraService $cameras,
    ) {
    }

    public function index(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        Log::debug('CameraController.index', ['location_id' => $locationId]);

        $items = $this->cameras
            ->listForLocation($location)
            ->map(fn (Camera $camera): array => $this->cameras->toPayload($camera))
            ->values()
            ->all();

        return $this->successResponse(['cameras' => $items]);
    }

    public function update(
        UpdateCameraRequest $request,
        int $locationId,
        int $cameraId,
    ): JsonResponse {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('update', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $camera = Camera::query()
            ->where('tenant_id', $location->tenant_id)
            ->where('location_id', $location->location_id)
            ->where('camera_id', $cameraId)
            ->firstOrFail();

        $payload = $this->cameras->update(
            $location,
            $camera,
            $user,
            $request->validated(),
        );

        return $this->successResponse($payload);
    }
}
