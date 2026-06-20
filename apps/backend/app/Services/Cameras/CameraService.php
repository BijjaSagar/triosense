<?php

declare(strict_types=1);

namespace App\Services\Cameras;

use App\Models\Camera;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

final class CameraService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @return Collection<int, Camera>
     */
    public function listForLocation(Location $location): Collection
    {
        Log::info('CameraService.listForLocation', [
            'location_id' => $location->location_id,
            'tenant_id' => $location->tenant_id,
        ]);

        return Camera::query()
            ->where('tenant_id', $location->tenant_id)
            ->where('location_id', $location->location_id)
            ->orderBy('camera_id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(
        Location $location,
        Camera $camera,
        User $actor,
        array $changes,
    ): array {
        if ((int) $camera->location_id !== (int) $location->location_id
            || (int) $camera->tenant_id !== (int) $location->tenant_id) {
            abort(404);
        }

        $allowed = array_intersect_key($changes, array_flip([
            'name', 'role', 'source_type', 'rtsp_url', 'tripwire_json', 'status',
        ]));

        if ($allowed === []) {
            return $this->toPayload($camera);
        }

        $before = $this->toPayload($camera);

        Log::info('CameraService.update', [
            'camera_id' => $camera->camera_id,
            'location_id' => $location->location_id,
            'changes' => array_keys($allowed),
            'user_id' => $actor->user_id,
        ]);

        $camera->fill($allowed);
        $camera->save();

        $this->auditLogger->record(
            action: 'camera.updated',
            entity: $camera,
            before: $before,
            after: $this->toPayload($camera),
            actor: $actor,
            locationId: (int) $location->location_id,
        );

        return $this->toPayload($camera);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(Camera $camera): array
    {
        return [
            'camera_id' => $camera->camera_id,
            'location_id' => $camera->location_id,
            'edge_device_id' => $camera->edge_device_id,
            'name' => $camera->name,
            'role' => $camera->role,
            'source_type' => $camera->source_type ?? 'rtsp',
            'rtsp_url' => $camera->rtsp_url,
            'tripwire' => $camera->tripwire_json,
            'status' => $camera->status,
            'last_frame_at' => $camera->last_frame_at?->toIso8601String(),
        ];
    }
}
