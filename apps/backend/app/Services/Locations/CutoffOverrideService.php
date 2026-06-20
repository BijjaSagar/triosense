<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Broadcasting\LocationStateUpdated;
use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Status;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Fifo\LiveStateReader;
use App\Services\Fifo\LocationRedisStateWriter;
use App\Services\Notifications\PushNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Manual cutoff overrides with audit logging.
 */
final class CutoffOverrideService
{
    public function __construct(
        private readonly LiveStateReader $liveStateReader,
        private readonly LocationRedisStateWriter $redisWriter,
        private readonly AuditLogger $auditLogger,
        private readonly PushNotificationService $pushNotifications,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function applyOverride(
        Location $location,
        User $actor,
        string $action,
        ?int $cutoffPosition,
        string $reason,
    ): array {
        Log::info('CutoffOverrideService.applyOverride', [
            'location_id' => $location->location_id,
            'action' => $action,
            'user_id' => $actor->user_id,
        ]);

        $state = $this->liveStateReader->read($location);
        $previous = $this->liveStateReader->readSnapshot((int) $location->location_id);

        $decision = match ($action) {
            'force_open' => new Decision(Status::OPEN, null, 'operator_force_open'),
            'force_close' => new Decision(Status::CLOSED, $state->queueHead, 'operator_force_close'),
            'set_cutoff' => new Decision(
                Status::CUTOFF_DECLARED,
                $cutoffPosition ?? throw new RuntimeException('cutoff_position required for set_cutoff'),
                'operator_set_cutoff',
            ),
            default => throw new RuntimeException("Unknown override action: {$action}"),
        };

        $before = [
            'status' => $previous->status->value,
            'cutoff_position' => $previous->cutoffPosition,
        ];

        $this->redisWriter->apply((int) $location->location_id, $decision, $state);

        $after = [
            'status' => $decision->status->value,
            'cutoff_position' => $decision->cutoffPosition,
        ];

        $this->auditLogger->record(
            action: 'cutoff.overridden',
            entity: $location,
            before: $before,
            after: $after,
            actor: $actor,
            locationId: (int) $location->location_id,
            reason: $reason,
        );

        Event::dispatch(new LocationStateUpdated(
            locationId: (int) $location->location_id,
            asOf: CarbonImmutable::now(),
            state: $state,
            decision: $decision,
            cause: 'override',
        ));

        $this->pushNotifications->sendToUsers(
            [$actor->user_id],
            'Override applied',
            sprintf('%s: %s', $location->name, $action),
            [
                'location_id' => (string) $location->location_id,
                'type' => 'OVERRIDE_APPLIED',
                'action' => $action,
            ],
        );

        return app(LocationStateService::class)->getState($location->fresh() ?? $location);
    }
}
