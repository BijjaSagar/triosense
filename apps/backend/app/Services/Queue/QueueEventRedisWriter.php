<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Domain\Fifo\LocationRedisKeys;
use App\Domain\Fifo\Status;
use App\Models\DailyQuota;
use App\Models\EdgeDevice;
use App\Models\Location;
use App\Models\QueueEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Applies queue event side effects to Redis after durable insert.
 */
final class QueueEventRedisWriter
{
    private readonly string $enterScript;

    private readonly string $issueScript;

    private readonly string $exitScript;

    public function __construct(
        private readonly RollingRateTracker $rateTracker,
    ) {
        $this->enterScript = (string) file_get_contents(base_path('scripts/lua/apply_enter_event.lua'));
        $this->issueScript = (string) file_get_contents(base_path('scripts/lua/apply_issue_event.lua'));
        $this->exitScript = (string) file_get_contents(base_path('scripts/lua/apply_exit_event.lua'));
    }

    public function applyEnter(QueueEvent $event): void
    {
        $locationId = (int) $event->location_id;
        $rate = $this->rateTracker->recordEnter($locationId, CarbonImmutable::parse($event->occurred_at));
        $asOfMs = (string) CarbonImmutable::parse($event->received_at)->getTimestampMs();

        Redis::connection()->command('eval', [
            $this->enterScript,
            3,
            LocationRedisKeys::queueTail($locationId),
            LocationRedisKeys::lastEventAt($locationId),
            LocationRedisKeys::arrivalRatePerMin($locationId),
            $asOfMs,
            (string) round($rate, 3),
        ]);

        Log::info('QueueEventRedisWriter.applyEnter', [
            'location_id' => $locationId,
            'queue_event_id' => $event->queue_event_id,
        ]);
    }

    public function applyIssue(QueueEvent $event): void
    {
        $locationId = (int) $event->location_id;
        $quota = $this->resolveQuota($locationId);
        $rate = $this->rateTracker->recordIssue($locationId, CarbonImmutable::parse($event->occurred_at));
        $asOfMs = (string) CarbonImmutable::parse($event->received_at)->getTimestampMs();

        Redis::connection()->command('eval', [
            $this->issueScript,
            5,
            LocationRedisKeys::issued($locationId),
            LocationRedisKeys::queueHead($locationId),
            LocationRedisKeys::tokensRemaining($locationId),
            LocationRedisKeys::lastEventAt($locationId),
            LocationRedisKeys::issuanceRatePerMin($locationId),
            (string) $quota,
            $asOfMs,
            (string) round($rate, 3),
        ]);

        Log::info('QueueEventRedisWriter.applyIssue', [
            'location_id' => $locationId,
            'queue_event_id' => $event->queue_event_id,
        ]);
    }

    public function applyExit(QueueEvent $event): void
    {
        $locationId = (int) $event->location_id;
        $this->rateTracker->recordExit($locationId, CarbonImmutable::parse($event->occurred_at));
        $asOfMs = (string) CarbonImmutable::parse($event->received_at)->getTimestampMs();

        Redis::connection()->command('eval', [
            $this->exitScript,
            3,
            LocationRedisKeys::queueTail($locationId),
            LocationRedisKeys::queueHead($locationId),
            LocationRedisKeys::lastEventAt($locationId),
            $asOfMs,
        ]);

        Log::info('QueueEventRedisWriter.applyExit', [
            'location_id' => $locationId,
            'queue_event_id' => $event->queue_event_id,
        ]);
    }

    public function seedLocation(int $locationId, int $tenantId): void
    {
        $quota = $this->resolveQuota($locationId);
        $status = Status::OPEN->value;
        $nowMs = (string) CarbonImmutable::now()->getTimestampMs();

        Redis::mset([
            LocationRedisKeys::quota($locationId) => (string) $quota,
            LocationRedisKeys::issued($locationId) => '0',
            LocationRedisKeys::tokensRemaining($locationId) => (string) $quota,
            LocationRedisKeys::queueHead($locationId) => '0',
            LocationRedisKeys::queueTail($locationId) => '0',
            LocationRedisKeys::status($locationId) => $status,
            LocationRedisKeys::issuanceRatePerMin($locationId) => '0',
            LocationRedisKeys::arrivalRatePerMin($locationId) => '0',
            LocationRedisKeys::lastEventAt($locationId) => $nowMs,
        ]);

        Log::info('QueueEventRedisWriter.seedLocation', [
            'location_id' => $locationId,
            'tenant_id' => $tenantId,
            'quota' => $quota,
        ]);
    }

    public function isCold(int $locationId): bool
    {
        $lastEvent = Redis::get(LocationRedisKeys::lastEventAt($locationId));

        return $lastEvent === null || $lastEvent === false;
    }

    private function resolveQuota(int $locationId): int
    {
        $cached = Redis::get(LocationRedisKeys::quota($locationId));
        if (is_string($cached) && $cached !== '') {
            return (int) $cached;
        }

        $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $dailyQuota = DailyQuota::query()
            ->withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->where('quota_date', $today)
            ->first();

        if ($dailyQuota !== null) {
            return (int) $dailyQuota->quota;
        }

        $location = Location::query()->withoutGlobalScopes()->find($locationId);

        return (int) ($location?->default_quota ?? 0);
    }
}
