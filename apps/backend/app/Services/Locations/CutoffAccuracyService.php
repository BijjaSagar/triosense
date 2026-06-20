<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Domain\Fifo\Status;
use App\Models\CutoffEvent;
use App\Models\DailyQuota;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Compares predicted cutoff (from shadow/live cutoff_events) vs actual closure.
 */
final class CutoffAccuracyService
{
    /**
     * @return array<string, mixed>
     */
    public function getAccuracyReport(Location $location, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= CarbonImmutable::now($location->tenant?->timezone ?? 'Asia/Kolkata')->subDays(30)->startOfDay();
        $to ??= CarbonImmutable::now($location->tenant?->timezone ?? 'Asia/Kolkata')->endOfDay();

        Log::debug('CutoffAccuracyService.getAccuracyReport', [
            'location_id' => $location->location_id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        $predictions = CutoffEvent::query()
            ->where('tenant_id', $location->tenant_id)
            ->where('location_id', $location->location_id)
            ->where('new_status', Status::CUTOFF_DECLARED->value)
            ->whereBetween('decided_at', [$from, $to])
            ->orderBy('decided_at')
            ->get();

        $dailyRows = [];

        foreach ($predictions->groupBy(static fn (CutoffEvent $e): string => $e->decided_at->toDateString()) as $date => $events) {
            /** @var CutoffEvent $latest */
            $latest = $events->sortByDesc('decided_at')->first();

            $quota = DailyQuota::query()
                ->where('tenant_id', $location->tenant_id)
                ->where('location_id', $location->location_id)
                ->whereDate('quota_date', $date)
                ->first();

            $actualClosurePosition = $this->resolveActualClosurePosition($quota, $latest);

            $predicted = $latest->cutoff_position;
            $delta = ($predicted !== null && $actualClosurePosition !== null)
                ? abs($predicted - $actualClosurePosition)
                : null;

            $dailyRows[] = [
                'date' => $date,
                'mode' => $latest->mode,
                'predicted_cutoff_position' => $predicted,
                'actual_closure_position' => $actualClosurePosition,
                'delta_positions' => $delta,
                'within_tolerance' => $delta !== null ? $delta <= 10 : null,
                'predicted_at' => $latest->decided_at->toIso8601String(),
                'closed_at' => $quota?->closed_at?->toIso8601String(),
                'closed_reason' => $quota?->closed_reason,
            ];
        }

        $deltas = array_values(array_filter(
            array_column($dailyRows, 'delta_positions'),
            static fn (?int $d): bool => $d !== null,
        ));

        return [
            'location_id' => $location->location_id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'days_with_predictions' => count($dailyRows),
                'days_within_tolerance' => count(array_filter(
                    $dailyRows,
                    static fn (array $row): bool => $row['within_tolerance'] === true,
                )),
                'median_delta' => $this->median($deltas),
                'max_delta' => $deltas !== [] ? max($deltas) : null,
            ],
            'daily' => $dailyRows,
        ];
    }

    private function resolveActualClosurePosition(?DailyQuota $quota, CutoffEvent $prediction): ?int
    {
        if ($quota === null) {
            return null;
        }

        if ($quota->closed_at === null) {
            return null;
        }

        // Actual closure position = queue_head + tokens_remaining at close
        // Approximated from issued count when quota exhausted.
        if ($quota->closed_reason === 'quota_exhausted') {
            return (int) $quota->issued;
        }

        return $prediction->queue_head + $prediction->tokens_remaining - 1;
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }

        return (float) $values[$mid];
    }
}
