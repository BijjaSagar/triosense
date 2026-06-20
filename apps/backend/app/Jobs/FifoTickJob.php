<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Fifo\FifoTickService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the FIFO decision loop for one location.
 *
 * Dispatched once per second per active location by the scheduler.
 */
final class FifoTickJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 1;

    public function __construct(
        public readonly int $locationId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'fifo-tick:'.$this->locationId;
    }

    public function handle(FifoTickService $fifoTickService): void
    {
        Log::debug('FifoTickJob.handle', ['location_id' => $this->locationId]);

        $fifoTickService->tick($this->locationId);
    }
}
