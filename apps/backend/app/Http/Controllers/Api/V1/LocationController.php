<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\QueueEvent;
use App\Services\Locations\LocationStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LocationController extends Controller
{
    public function __construct(
        private readonly LocationStateService $locationState,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Log::debug('LocationController.index', ['user_id' => $user?->user_id]);

        $query = Location::query()->where('status', 'active');

        if ($user !== null && ! $user->hasRole('ttd_admin')) {
            $assigned = $user->assignedLocationIds();
            $query->whereIn('location_id', $assigned);
        }

        $locations = $query
            ->orderBy('location_id')
            ->get(['location_id', 'name', 'short_code', 'mode', 'status'])
            ->map(static fn (Location $location): array => [
                'location_id' => $location->location_id,
                'name' => $location->name,
                'short_code' => $location->short_code,
                'mode' => $location->mode,
                'status' => $location->status,
            ])
            ->values()
            ->all();

        return $this->successResponse(['locations' => $locations]);
    }

    public function state(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        Log::debug('LocationController.state', [
            'user_id' => $request->user()?->user_id,
            'location_id' => $locationId,
        ]);

        return $this->successResponse($this->locationState->getState($location));
    }

    public function events(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $eventType = $request->query('event_type');

        Log::debug('LocationController.events', [
            'user_id' => $user->user_id,
            'location_id' => $locationId,
            'per_page' => $perPage,
        ]);

        $paginator = $this->locationState->paginateEvents(
            tenantId: (int) $user->tenant_id,
            locationId: $locationId,
            perPage: $perPage,
            eventType: is_string($eventType) ? $eventType : null,
        );

        /** @var list<array<string, mixed>> $items */
        $items = [];
        foreach ($paginator->items() as $event) {
            $items[] = [
                'queue_event_id' => $event->queue_event_id,
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'received_at' => $event->received_at->toIso8601String(),
                'edge_device_id' => $event->edge_device_id,
                'camera_id' => $event->camera_id,
                'track_id' => $event->track_id,
                'confidence' => $event->confidence !== null ? (float) $event->confidence : null,
            ];
        }

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
