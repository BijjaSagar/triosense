<?php

declare(strict_types=1);

namespace App\Services\Fifo;

use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Mode;
use App\Domain\Fifo\Status;
use App\Models\CutoffEvent;
use App\Models\Location;
use App\Models\UserLocationAssignment;
use App\Mqtt\MqttCommandPublisher;
use App\Services\Announcement\AnnouncementService;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Side effects triggered on FIFO status transitions.
 *
 * Gated by locations.mode: SHADOW suppresses MQTT + PA; LIVE enables full effects.
 *
 * @see docs/adr/0003-fifo-tick-side-effects.md
 */
final class FifoTickSideEffectHandler
{
    public function __construct(
        private readonly MqttCommandPublisher $mqttPublisher,
        private readonly AnnouncementService $announcements,
        private readonly PushNotificationService $pushNotifications,
    ) {
    }

    public function handleStatusTransition(
        Location $location,
        Status $previousStatus,
        Decision $decision,
        LiveState $state,
        ?CutoffEvent $cutoffEvent = null,
    ): void {
        Log::info('FifoTickSideEffectHandler.handle', [
            'location_id' => $location->location_id,
            'mode' => $location->mode,
            'previous' => $previousStatus->value,
            'new' => $decision->status->value,
        ]);

        if ($location->mode === Mode::SHADOW->value) {
            Log::debug('FifoTickSideEffectHandler.shadow_mode_suppressed', [
                'location_id' => $location->location_id,
            ]);

            return;
        }

        if ($location->mode !== Mode::LIVE->value) {
            return;
        }

        if ($decision->status === Status::CUTOFF_DECLARED && $decision->cutoffPosition !== null) {
            $this->mqttPublisher->closeEntry(
                (int) $location->location_id,
                $decision->cutoffPosition,
            );
        }

        $this->announcements->announceStatusChange(
            $location,
            $previousStatus,
            $decision,
            $state,
            $cutoffEvent,
        );

        $this->notifySupervisors($location, $previousStatus, $decision);
    }

    private function notifySupervisors(
        Location $location,
        Status $previousStatus,
        Decision $decision,
    ): void {
        if ($decision->status !== Status::APPROACHING_CUTOFF
            && $decision->status !== Status::CUTOFF_DECLARED) {
            return;
        }

        $userIds = UserLocationAssignment::query()
            ->where('tenant_id', $location->tenant_id)
            ->where('location_id', $location->location_id)
            ->pluck('user_id')
            ->all();

        $title = $decision->status === Status::CUTOFF_DECLARED
            ? 'Cutoff declared'
            : 'Approaching cutoff';

        $body = $decision->status === Status::CUTOFF_DECLARED
            ? sprintf('%s: last guaranteed token #%s', $location->name, $decision->cutoffPosition ?? '—')
            : sprintf('%s: counter approaching cutoff', $location->name);

        $this->pushNotifications->sendToUsers($userIds, $title, $body, [
            'location_id' => (string) $location->location_id,
            'status' => $decision->status->value,
            'type' => $decision->status === Status::CUTOFF_DECLARED
                ? 'CUTOFF_DECLARED'
                : 'APPROACHING_CUTOFF',
        ]);
    }
}
