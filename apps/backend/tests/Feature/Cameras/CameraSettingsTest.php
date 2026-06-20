<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function cameraAdminToken(): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => 'ops@ttd.gov.in',
        'password' => 'password',
    ]);

    return (string) $response->json('data.token');
}

it('lists cameras for a location scoped to tenant', function (): void {
    $token = cameraAdminToken();

    $response = $this->getJson('/api/v1/locations/1/cameras', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.cameras.0.camera_id', 1)
        ->assertJsonPath('data.cameras.0.source_type', 'webcam')
        ->assertJsonPath('data.cameras.0.tripwire.direction', 'down');
});

it('updates camera tripwire and source settings', function (): void {
    $token = cameraAdminToken();

    $response = $this->patchJson('/api/v1/locations/1/cameras/1', [
        'name' => 'Updated Webcam',
        'source_type' => 'webcam',
        'rtsp_url' => '0',
        'tripwire_json' => [
            'line' => [[100, 200], [900, 200]],
            'direction' => 'right',
        ],
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Updated Webcam')
        ->assertJsonPath('data.tripwire.direction', 'right');

    $this->assertDatabaseHas('cameras', [
        'camera_id' => 1,
        'name' => 'Updated Webcam',
        'source_type' => 'webcam',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'camera.updated',
        'entity_type' => \App\Models\Camera::class,
        'entity_id' => 1,
    ]);
});

it('rejects camera update without authentication', function (): void {
    $this->patchJson('/api/v1/locations/1/cameras/1', [
        'name' => 'Should Fail',
    ])->assertUnauthorized();
});

it('returns not found for camera outside location', function (): void {
    $token = cameraAdminToken();

    $this->patchJson('/api/v1/locations/1/cameras/17', [
        'name' => 'Wrong location',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertNotFound();
});

it('includes source_type in edge device config payload', function (): void {
    $response = $this->getJson('/api/v1/edge/edge-sim-01/config', [
        'X-Edge-Api-Key' => 'edge-dev-key-vsn-01',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.cameras.0.source_type', 'webcam')
        ->assertJsonPath('data.cameras.0.camera_id', 1);
});
