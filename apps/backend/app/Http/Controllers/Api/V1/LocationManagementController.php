<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CutoffOverrideRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Announcement;
use App\Models\CutoffEvent;
use App\Models\Location;
use App\Services\Locations\CrossCounterRecommendationService;
use App\Services\Locations\CutoffAccuracyService;
use App\Services\Locations\CutoffOverrideService;
use App\Services\Locations\LocationStateService;
use App\Services\Locations\LocationUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LocationManagementController extends Controller
{
    public function __construct(
        private readonly LocationUpdateService $locationUpdate,
        private readonly CutoffOverrideService $cutoffOverride,
        private readonly CutoffAccuracyService $cutoffAccuracy,
        private readonly CrossCounterRecommendationService $crossCounter,
        private readonly LocationStateService $locationState,
    ) {
    }

    public function show(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        return $this->successResponse([
            'location_id' => $location->location_id,
            'name' => $location->name,
            'short_code' => $location->short_code,
            'mode' => $location->mode,
            'festival_mode' => $location->festival_mode,
            'default_quota' => $location->default_quota,
            'status' => $location->status,
            'state' => $this->locationState->getState($location),
        ]);
    }

    public function update(UpdateLocationRequest $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('update', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $payload = $this->locationUpdate->update($location, $user, $request->validated());

        return $this->successResponse($payload);
    }

    public function cutoffAccuracy(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        Log::debug('LocationManagementController.cutoffAccuracy', [
            'location_id' => $locationId,
        ]);

        return $this->successResponse(
            $this->cutoffAccuracy->getAccuracyReport($location),
        );
    }

    public function cutoffEvents(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $mode = $request->query('mode');

        $query = CutoffEvent::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('location_id', $locationId)
            ->orderByDesc('decided_at');

        if (is_string($mode) && $mode !== '') {
            $query->where('mode', $mode);
        }

        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => collect($paginator->items())->map(static fn (CutoffEvent $e): array => [
                'cutoff_event_id' => $e->cutoff_event_id,
                'decided_at' => $e->decided_at->toIso8601String(),
                'mode' => $e->mode,
                'previous_status' => $e->previous_status,
                'new_status' => $e->new_status,
                'cutoff_position' => $e->cutoff_position,
                'tokens_remaining' => $e->tokens_remaining,
                'reason' => $e->reason,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function override(CutoffOverrideRequest $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('override', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $validated = $request->validated();

        return $this->successResponse(
            $this->cutoffOverride->applyOverride(
                $location,
                $user,
                $validated['action'],
                isset($validated['cutoff_position']) ? (int) $validated['cutoff_position'] : null,
                $validated['reason'],
            ),
        );
    }

    public function announcements(Request $request, int $locationId): JsonResponse
    {
        $location = Location::query()->findOrFail($locationId);
        $this->authorize('view', $location);

        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $paginator = Announcement::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('location_id', $locationId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->successResponse([
            'items' => collect($paginator->items())->map(static fn (Announcement $a): array => [
                'announcement_id' => $a->announcement_id,
                'language' => $a->language,
                'text_played' => $a->text_played,
                'trigger_type' => $a->trigger_type,
                'status' => $a->status,
                'played_at' => $a->played_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function crossCounterRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        return $this->successResponse([
            'recommendations' => $this->crossCounter->getRecommendations((int) $user->tenant_id),
        ]);
    }
}
