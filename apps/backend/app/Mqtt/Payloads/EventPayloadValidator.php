<?php

declare(strict_types=1);

namespace App\Mqtt\Payloads;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Validates and normalises MQTT event payloads per API_CONTRACTS.md §3.2.
 */
final class EventPayloadValidator
{
    /**
     * @return array{
     *     v: int,
     *     device_uid: string,
     *     camera_id: int|null,
     *     occurred_at: CarbonImmutable,
     *     track_id: string|null,
     *     confidence: float|null,
     *     metadata: array<string, mixed>|null
     * }|null
     */
    public function validate(string $rawPayload, string $eventType): ?array
    {
        try {
            /** @var array<string, mixed>|null $data */
            $data = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::warning('EventPayloadValidator.invalid_json', [
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! is_array($data)) {
            Log::warning('EventPayloadValidator.not_object', ['event_type' => $eventType]);

            return null;
        }

        $version = $data['v'] ?? null;
        if (! is_int($version) && ! (is_string($version) && ctype_digit($version))) {
            Log::warning('EventPayloadValidator.missing_version', ['event_type' => $eventType]);

            return null;
        }

        $deviceUid = $data['device_uid'] ?? null;
        if (! is_string($deviceUid) || $deviceUid === '') {
            Log::warning('EventPayloadValidator.missing_device_uid', ['event_type' => $eventType]);

            return null;
        }

        $occurredAtRaw = $data['occurred_at'] ?? null;
        if (! is_string($occurredAtRaw) || $occurredAtRaw === '') {
            Log::warning('EventPayloadValidator.missing_occurred_at', [
                'event_type' => $eventType,
                'device_uid' => $deviceUid,
            ]);

            return null;
        }

        try {
            $occurredAt = CarbonImmutable::parse($occurredAtRaw);
        } catch (\Throwable) {
            Log::warning('EventPayloadValidator.invalid_occurred_at', [
                'event_type' => $eventType,
                'device_uid' => $deviceUid,
                'occurred_at' => $occurredAtRaw,
            ]);

            return null;
        }

        $cameraId = $data['camera_id'] ?? null;
        $trackId = $data['track_id'] ?? null;
        $confidence = $data['confidence'] ?? null;
        $metadata = $data['metadata'] ?? null;

        return [
            'v' => (int) $version,
            'device_uid' => $deviceUid,
            'camera_id' => is_numeric($cameraId) ? (int) $cameraId : null,
            'occurred_at' => $occurredAt,
            'track_id' => is_string($trackId) ? $trackId : null,
            'confidence' => is_numeric($confidence) ? (float) $confidence : null,
            'metadata' => is_array($metadata) ? $metadata : null,
        ];
    }

    /**
     * @return array{
     *     v: int,
     *     device_uid: string,
     *     timestamp: CarbonImmutable,
     *     uptime_seconds: int|null,
     *     cpu_percent: float|null,
     *     mem_percent: float|null,
     *     temp_celsius: float|null,
     *     cameras: list<array<string, mixed>>|null,
     *     buffer_size: int|null
     * }|null
     */
    public function validateHeartbeat(string $rawPayload): ?array
    {
        try {
            /** @var array<string, mixed>|null $data */
            $data = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::warning('EventPayloadValidator.heartbeat_invalid_json', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $deviceUid = $data['device_uid'] ?? null;
        $timestampRaw = $data['timestamp'] ?? null;

        if (! is_string($deviceUid) || $deviceUid === '' || ! is_string($timestampRaw) || $timestampRaw === '') {
            Log::warning('EventPayloadValidator.heartbeat_missing_fields');

            return null;
        }

        try {
            $timestamp = CarbonImmutable::parse($timestampRaw);
        } catch (\Throwable) {
            Log::warning('EventPayloadValidator.heartbeat_invalid_timestamp', [
                'device_uid' => $deviceUid,
            ]);

            return null;
        }

        $version = $data['v'] ?? 1;
        $cameras = $data['cameras'] ?? null;

        return [
            'v' => (int) $version,
            'device_uid' => $deviceUid,
            'timestamp' => $timestamp,
            'uptime_seconds' => is_numeric($data['uptime_seconds'] ?? null) ? (int) $data['uptime_seconds'] : null,
            'cpu_percent' => is_numeric($data['cpu_percent'] ?? null) ? (float) $data['cpu_percent'] : null,
            'mem_percent' => is_numeric($data['mem_percent'] ?? null) ? (float) $data['mem_percent'] : null,
            'temp_celsius' => is_numeric($data['temp_celsius'] ?? null) ? (float) $data['temp_celsius'] : null,
            'cameras' => is_array($cameras) ? $cameras : null,
            'buffer_size' => is_numeric($data['buffer_size'] ?? null) ? (int) $data['buffer_size'] : null,
        ];
    }
}
