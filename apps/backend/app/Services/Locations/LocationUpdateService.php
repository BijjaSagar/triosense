<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Updates location settings (mode, festival_mode).
 */
final class LocationUpdateService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(Location $location, User $actor, array $changes): array
    {
        $allowed = array_intersect_key($changes, array_flip([
            'mode', 'festival_mode', 'default_quota',
        ]));

        if ($allowed === []) {
            return $this->toPayload($location);
        }

        $before = $this->toPayload($location);

        Log::info('LocationUpdateService.update', [
            'location_id' => $location->location_id,
            'changes' => array_keys($allowed),
            'user_id' => $actor->user_id,
        ]);

        $location->fill($allowed);
        $location->save();

        $this->auditLogger->record(
            action: 'location.updated',
            entity: $location,
            before: $before,
            after: $this->toPayload($location),
            actor: $actor,
            locationId: (int) $location->location_id,
        );

        return $this->toPayload($location);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Location $location): array
    {
        return [
            'location_id' => $location->location_id,
            'name' => $location->name,
            'mode' => $location->mode,
            'festival_mode' => $location->festival_mode,
            'default_quota' => $location->default_quota,
        ];
    }
}
