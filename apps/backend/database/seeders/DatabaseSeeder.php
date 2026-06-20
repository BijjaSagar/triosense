<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTenantAndLocations();
        $this->call(RolePermissionSeeder::class);
        $this->seedEdgeDevices();
        $this->seedOperatorUser();
        $this->seedDailyQuotas();
        $this->call(AnnouncementTemplateSeeder::class);
    }

    private function seedTenantAndLocations(): void
    {
        DB::table('tenants')->insertOrIgnore([
            [
                'tenant_id' => 1,
                'name' => 'Tirumala Tirupati Devasthanams',
                'slug' => 'ttd',
                'contact_email' => 'ops@ttd.gov.in',
                'timezone' => 'Asia/Kolkata',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('locations')->insertOrIgnore([
            [
                'location_id' => 1,
                'tenant_id' => 1,
                'name' => 'Vishnu Nivasam',
                'short_code' => 'VSN',
                'address' => 'Opposite Tirupati Railway Station, Tirupati, AP 517501',
                'latitude' => 13.6315000,
                'longitude' => 79.4192000,
                'opens_at' => '05:00:00',
                'closes_at' => '12:00:00',
                'default_quota' => 5000,
                'mode' => 'shadow',
                'festival_mode' => false,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'location_id' => 2,
                'tenant_id' => 1,
                'name' => 'Srinivasam Complex',
                'short_code' => 'SRN',
                'address' => 'Opposite Central Bus Stand, Tirupati, AP 517501',
                'latitude' => 13.6398000,
                'longitude' => 79.4147000,
                'opens_at' => '05:00:00',
                'closes_at' => '12:00:00',
                'default_quota' => 5000,
                'mode' => 'shadow',
                'festival_mode' => false,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'location_id' => 3,
                'tenant_id' => 1,
                'name' => 'Bhudevi Complex',
                'short_code' => 'BDV',
                'address' => 'Bhudevi Complex, Tirupati, AP 517501',
                'latitude' => 13.6480000,
                'longitude' => 79.4200000,
                'opens_at' => '05:00:00',
                'closes_at' => '12:00:00',
                'default_quota' => 5000,
                'mode' => 'shadow',
                'festival_mode' => false,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedEdgeDevices(): void
    {
        $devices = [
            [
                'edge_device_id' => 1,
                'location_id' => 1,
                'device_uid' => 'edge-sim-01',
                'short' => 'VSN',
                'api_key' => 'edge-dev-key-vsn-01',
            ],
            [
                'edge_device_id' => 2,
                'location_id' => 2,
                'device_uid' => 'edge-sim-02',
                'short' => 'SRN',
                'api_key' => 'edge-dev-key-srn-02',
            ],
            [
                'edge_device_id' => 3,
                'location_id' => 3,
                'device_uid' => 'edge-sim-03',
                'short' => 'BDV',
                'api_key' => 'edge-dev-key-bdv-03',
            ],
        ];

        foreach ($devices as $device) {
            DB::table('edge_devices')->insertOrIgnore([
                'edge_device_id' => $device['edge_device_id'],
                'tenant_id' => 1,
                'location_id' => $device['location_id'],
                'device_uid' => $device['device_uid'],
                'hardware_id' => 'sim-'.$device['short'],
                'ip_address' => '127.0.0.1',
                'firmware_version' => '0.1.0-sim',
                'last_heartbeat_at' => null,
                'status' => 'offline',
                'config_json' => json_encode([
                    'heartbeat_seconds' => 5,
                    'inference_fps' => 15,
                    'inference_confidence_threshold' => 0.5,
                    'inference_backend' => 'mock',
                    'stream_backend' => 'mock',
                    'model_path' => 'yolov8n.pt',
                    'rtsp_reconnect_seconds' => 5.0,
                ], JSON_THROW_ON_ERROR),
                'api_key_hash' => Hash::make($device['api_key']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seedCameras();
    }

    private function seedCameras(): void
    {
        $tripwire = json_encode([
            'line' => [[640, 720], [1280, 720]],
            'direction' => 'down',
        ], JSON_THROW_ON_ERROR);

        $cameras = [
            [
                'camera_id' => 17,
                'location_id' => 3,
                'edge_device_id' => 3,
                'name' => 'Bhudevi Entry Tripwire',
                'role' => 'entry_tripwire',
                'rtsp_url' => 'rtsp://127.0.0.1:8554/entry',
                'tripwire_json' => $tripwire,
            ],
            [
                'camera_id' => 18,
                'location_id' => 3,
                'edge_device_id' => 3,
                'name' => 'Bhudevi Counter Window',
                'role' => 'counter_window',
                'rtsp_url' => 'rtsp://127.0.0.1:8554/counter',
                'tripwire_json' => null,
            ],
        ];

        foreach ($cameras as $camera) {
            DB::table('cameras')->insertOrIgnore([
                'camera_id' => $camera['camera_id'],
                'tenant_id' => 1,
                'location_id' => $camera['location_id'],
                'edge_device_id' => $camera['edge_device_id'],
                'name' => $camera['name'],
                'role' => $camera['role'],
                'rtsp_url' => $camera['rtsp_url'],
                'tripwire_json' => $camera['tripwire_json'],
                'status' => 'active',
                'last_frame_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedOperatorUser(): void
    {
        DB::table('users')->insertOrIgnore([
            'user_id' => 1,
            'tenant_id' => 1,
            'name' => 'TTD Operations',
            'email' => 'ops@ttd.gov.in',
            'phone' => null,
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1, 2, 3] as $locationId) {
            DB::table('user_location_assignments')->insertOrIgnore([
                'assignment_id' => $locationId,
                'tenant_id' => 1,
                'user_id' => 1,
                'location_id' => $locationId,
                'can_override' => true,
                'created_at' => now(),
            ]);
        }

        setPermissionsTeamId(1);

        /** @var User $user */
        $user = User::query()->withoutGlobalScopes()->findOrFail(1);
        $user->assignRole('ttd_admin');
    }

    private function seedDailyQuotas(): void
    {
        $today = now('Asia/Kolkata')->toDateString();

        foreach ([1, 2, 3] as $locationId) {
            DB::table('daily_quotas')->insertOrIgnore([
                'daily_quota_id' => $locationId,
                'tenant_id' => 1,
                'location_id' => $locationId,
                'quota_date' => $today,
                'quota' => 5000,
                'issued' => 0,
                'opened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
