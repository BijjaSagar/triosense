<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use App\Services\Locations\CrossCounterRecommendationService;
use App\Services\Locations\CutoffAccuracyService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('returns cutoff accuracy report structure', function () {
    $location = Location::query()->findOrFail(3);
    $report = app(CutoffAccuracyService::class)->getAccuracyReport($location);

    expect($report)->toHaveKeys(['location_id', 'from', 'to', 'summary', 'daily'])
        ->and($report['location_id'])->toBe(3)
        ->and($report['summary'])->toHaveKeys([
            'days_with_predictions',
            'days_within_tolerance',
            'median_delta',
            'max_delta',
        ]);
});

it('documents shadow mode on locations by default', function () {
    $location = Location::query()->findOrFail(3);
    expect($location->mode)->toBe('shadow');
});

it('returns empty cross-counter recommendations when festival mode active', function () {
    Location::query()->update(['festival_mode' => true]);

    $service = app(CrossCounterRecommendationService::class);
    $recs = $service->getRecommendations(1);

    expect($recs)->toBe([]);
});

it('registers fcm token for authenticated user', function () {
    $user = User::query()->where('email', 'ops@ttd.gov.in')->firstOrFail();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/users/me/fcm-token', [
            'fcm_token' => 'test-fcm-token-abc123',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->fcm_token)->toBe('test-fcm-token-abc123');
});

it('returns cutoff accuracy via api', function () {
    $user = User::query()->where('email', 'ops@ttd.gov.in')->firstOrFail();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/locations/3/cutoff-accuracy');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['summary', 'daily']]);
});
