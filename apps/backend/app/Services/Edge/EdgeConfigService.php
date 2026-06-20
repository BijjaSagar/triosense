<?php

declare(strict_types=1);

namespace App\Services\Edge;

use App\Models\EdgeDevice;
use Illuminate\Support\Facades\Log;

final class EdgeConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function buildConfigPayload(EdgeDevice $device): array
    {
        $device->loadMissing(['cameras']);

        $runtime = array_merge(
            [
                'heartbeat_seconds' => 5,
                'inference_fps' => 15,
                'inference_confidence_threshold' => 0.5,
                'inference_backend' => 'cpu',
                'stream_backend' => 'gstreamer',
                'model_path' => 'yolov8n.pt',
                'rtsp_reconnect_seconds' => 5.0,
            ],
            is_array($device->config_json) ? $device->config_json : [],
        );

        $cameras = $device->cameras
            ->where('status', '!=', 'disabled')
            ->sortBy('camera_id')
            ->map(static function ($camera): array {
                $tripwire = $camera->tripwire_json;
                $payload = [
                    'camera_id' => $camera->camera_id,
                    'name' => $camera->name,
                    'role' => $camera->role,
                    'source_type' => $camera->source_type ?? 'rtsp',
                    'rtsp_url' => $camera->rtsp_url,
                    'status' => $camera->status,
                ];
                if (is_array($tripwire) && $tripwire !== []) {
                    $payload['tripwire'] = $tripwire;
                }

                return $payload;
            })
            ->values()
            ->all();

        Log::info('edge config served', [
            'device_uid' => $device->device_uid,
            'camera_count' => count($cameras),
        ]);

        return [
            'device_uid' => $device->device_uid,
            'tenant_id' => $device->tenant_id,
            'location_id' => $device->location_id,
            'runtime' => $runtime,
            'cameras' => $cameras,
        ];
    }
}
