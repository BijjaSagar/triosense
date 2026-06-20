<?php

declare(strict_types=1);

namespace App\Mqtt;

use App\Mqtt\Handlers\EnterEventHandler;
use App\Mqtt\Handlers\ExitEventHandler;
use App\Mqtt\Handlers\HeartbeatHandler;
use App\Mqtt\Handlers\IssueEventHandler;
use Illuminate\Support\Facades\Log;

/**
 * Routes incoming MQTT messages to the appropriate handler by topic pattern.
 */
final class MqttTopicRouter
{
    public function __construct(
        private readonly EnterEventHandler $enterHandler,
        private readonly ExitEventHandler $exitHandler,
        private readonly IssueEventHandler $issueHandler,
        private readonly HeartbeatHandler $heartbeatHandler,
        private readonly string $topicPrefix,
    ) {
    }

    public function dispatch(string $topic, string $payload): void
    {
        Log::debug('MqttTopicRouter.dispatch', ['topic' => $topic]);

        $pattern = '#^'.preg_quote($this->topicPrefix, '#').'/loc/(\d+)/#';

        if (! preg_match($pattern, $topic, $matches)) {
            Log::warning('MqttTopicRouter.unmatched_topic', ['topic' => $topic]);

            return;
        }

        $locationId = (int) $matches[1];
        $suffix = substr($topic, strlen($matches[0]));

        match (true) {
            $suffix === 'event/enter' => $this->enterHandler->handle($locationId, $payload),
            $suffix === 'event/exit' => $this->exitHandler->handle($locationId, $payload),
            $suffix === 'event/issue' => $this->issueHandler->handle($locationId, $payload),
            preg_match('#^edge/([^/]+)/heartbeat$#', $suffix, $hbMatches) === 1
                => $this->heartbeatHandler->handle($locationId, $hbMatches[1], $payload),
            default => Log::debug('MqttTopicRouter.ignored_topic', ['topic' => $topic]),
        };
    }
}
