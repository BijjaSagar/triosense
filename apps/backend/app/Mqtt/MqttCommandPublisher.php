<?php

declare(strict_types=1);

namespace App\Mqtt;

use App\Models\EdgeDevice;
use Illuminate\Support\Facades\Log;

/**
 * Publishes command messages to edge devices via MQTT.
 *
 * @see API_CONTRACTS.md §3.3
 */
final class MqttCommandPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(int $locationId, string $deviceUid, array $payload): bool
    {
        $prefix = (string) config('triosense.mqtt.topic_prefix', 'triosense');
        $topic = "{$prefix}/loc/{$locationId}/command/{$deviceUid}";

        $message = array_merge(['v' => 1], $payload);

        Log::info('MqttCommandPublisher.publish', [
            'topic' => $topic,
            'action' => $message['action'] ?? null,
        ]);

        if (! $this->isEnabled()) {
            Log::debug('MqttCommandPublisher.skipped_disabled', ['topic' => $topic]);

            return false;
        }

        try {
            /** @var \PhpMqtt\Client\MqttClient|null $client */
            $client = app()->bound('mqtt.publisher')
                ? app('mqtt.publisher')
                : null;

            if ($client === null) {
                Log::warning('MqttCommandPublisher.no_client', ['topic' => $topic]);

                return false;
            }

            $client->publish($topic, json_encode($message, JSON_THROW_ON_ERROR), 1);

            return true;
        } catch (\Throwable $e) {
            Log::error('MqttCommandPublisher.failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function closeEntry(int $locationId, int $cutoffPosition): void
    {
        $devices = EdgeDevice::query()
            ->where('location_id', $locationId)
            ->where('status', '!=', 'retired')
            ->get();

        foreach ($devices as $device) {
            $this->publish($locationId, $device->device_uid, [
                'action' => 'close_entry',
                'cutoff_position' => $cutoffPosition,
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return filter_var(
            env('TRIOSENSE_MQTT_PUBLISH_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        );
    }
}
