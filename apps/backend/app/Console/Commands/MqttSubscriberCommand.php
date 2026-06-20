<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mqtt\MqttTopicRouter;
use App\Mqtt\Handlers\HeartbeatHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

final class MqttSubscriberCommand extends Command
{
    protected $signature = 'triosense:mqtt-subscribe';

    protected $description = 'Subscribe to TrioSense MQTT topics and persist queue events';

    public function handle(MqttTopicRouter $router, HeartbeatHandler $heartbeatHandler): never
    {
        $host = (string) config('triosense.mqtt.host');
        $port = (int) config('triosense.mqtt.port');
        $clientId = (string) config('triosense.mqtt.client_id');
        $prefix = (string) config('triosense.mqtt.topic_prefix');
        $useTls = (bool) config('triosense.mqtt.tls');

        Log::info('MqttSubscriberCommand.start', [
            'host' => $host,
            'port' => $port,
            'client_id' => $clientId,
            'prefix' => $prefix,
        ]);

        $this->info("Connecting to MQTT broker at {$host}:{$port}...");

        $client = new MqttClient($host, $port, $clientId);

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10);

        if ($useTls) {
            $settings = $settings->setUseTls(true);
        }

        $username = config('triosense.mqtt.username');
        $password = config('triosense.mqtt.password');
        if (is_string($username) && $username !== '') {
            $settings = $settings->setUsername($username);
        }
        if (is_string($password) && $password !== '') {
            $settings = $settings->setPassword($password);
        }

        $client->connect($settings, true);

        $topics = [
            "{$prefix}/loc/+/event/enter" => ['qos' => 1],
            "{$prefix}/loc/+/event/exit" => ['qos' => 1],
            "{$prefix}/loc/+/event/issue" => ['qos' => 1],
            "{$prefix}/loc/+/edge/+/heartbeat" => ['qos' => 0],
        ];

        foreach ($topics as $topic => $options) {
            $client->subscribe($topic, function (string $receivedTopic, string $message) use ($router): void {
                try {
                    $router->dispatch($receivedTopic, $message);
                } catch (\Throwable $exception) {
                    Log::error('MqttSubscriberCommand.handler_error', [
                        'topic' => $receivedTopic,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }, $options['qos']);
            $this->line("Subscribed: {$topic}");
        }

        $lastStaleCheck = time();

        // Long-running daemon — exits only on SIGINT/SIGTERM.
        while (true) { // @phpstan-ignore-line
            $client->loop(false, true);

            if (time() - $lastStaleCheck >= 30) {
                $heartbeatHandler->markStaleDevices();
                $lastStaleCheck = time();
            }

            usleep(50_000);
        }
    }
}
