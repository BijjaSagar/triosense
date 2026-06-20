<?php

declare(strict_types=1);

namespace App\Mqtt\Handlers;

use App\Mqtt\Payloads\EventPayloadValidator;
use App\Services\Queue\QueueEventPersister;
use App\Services\Queue\QueueEventRedisWriter;
use Illuminate\Support\Facades\Log;

final class ExitEventHandler
{
    public function __construct(
        private readonly EventPayloadValidator $validator,
        private readonly QueueEventPersister $persister,
        private readonly QueueEventRedisWriter $redisWriter,
    ) {
    }

    public function handle(int $locationId, string $payload): void
    {
        Log::debug('ExitEventHandler.handle', ['location_id' => $locationId]);

        $validated = $this->validator->validate($payload, 'exit');
        if ($validated === null) {
            return;
        }

        $event = $this->persister->persist($locationId, 'exit', $validated);
        if ($event === null) {
            return;
        }

        $this->redisWriter->applyExit($event);
    }
}
