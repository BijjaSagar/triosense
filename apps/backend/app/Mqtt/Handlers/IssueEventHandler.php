<?php

declare(strict_types=1);

namespace App\Mqtt\Handlers;

use App\Mqtt\Payloads\EventPayloadValidator;
use App\Services\Queue\QueueEventPersister;
use App\Services\Queue\QueueEventRedisWriter;
use Illuminate\Support\Facades\Log;

final class IssueEventHandler
{
    public function __construct(
        private readonly EventPayloadValidator $validator,
        private readonly QueueEventPersister $persister,
        private readonly QueueEventRedisWriter $redisWriter,
    ) {
    }

    public function handle(int $locationId, string $payload): void
    {
        Log::debug('IssueEventHandler.handle', ['location_id' => $locationId]);

        $validated = $this->validator->validate($payload, 'issue');
        if ($validated === null) {
            return;
        }

        $event = $this->persister->persist($locationId, 'issue', $validated);
        if ($event === null) {
            return;
        }

        $this->redisWriter->applyIssue($event);
    }
}
