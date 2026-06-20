<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('returns camera config for an authenticated edge device', function () {
    $response = $this->getJson('/api/v1/edge/edge-sim-03/config', [
        'X-Edge-Api-Key' => 'edge-dev-key-bdv-03',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.device_uid', 'edge-sim-03')
        ->assertJsonPath('data.location_id', 3)
        ->assertJsonPath('data.cameras.0.camera_id', 17)
        ->assertJsonPath('data.cameras.0.tripwire.direction', 'down')
        ->assertJsonPath('data.runtime.inference_fps', 15);
});

it('rejects edge config without api key', function () {
    $this->getJson('/api/v1/edge/edge-sim-03/config')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'edge_unauthorized');
});

it('rejects invalid edge api key', function () {
    $this->getJson('/api/v1/edge/edge-sim-03/config', [
        'X-Edge-Api-Key' => 'wrong-key',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'edge_unauthorized');
});

it('returns not found for unknown edge device', function () {
    $this->getJson('/api/v1/edge/edge-unknown-99/config', [
        'X-Edge-Api-Key' => 'edge-dev-key-bdv-03',
    ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'edge_not_found');
});
