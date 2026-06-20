<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('logs in with valid credentials and returns a sanctum token', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ops@ttd.gov.in',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'ops@ttd.gov.in')
        ->assertJsonPath('data.user.roles.0', 'ttd_admin')
        ->assertJsonStructure([
            'success',
            'data' => ['token', 'user', 'expires_at'],
            'meta' => ['request_id', 'timestamp'],
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ops@ttd.gov.in',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('returns the authenticated operator profile', function () {
    Sanctum::actingAs(User::query()->withoutGlobalScopes()->findOrFail(1));

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'ops@ttd.gov.in')
        ->assertJsonPath('data.locations', [1, 2, 3]);
});

it('logs out and revokes the current token', function () {
    $user = User::query()->withoutGlobalScopes()->findOrFail(1);
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
});

it('enforces tenant scoping on location policy', function () {
    $user = User::query()->withoutGlobalScopes()->findOrFail(1);
    setPermissionsTeamId($user->tenant_id);

    $location = Location::query()->withoutGlobalScopes()->findOrFail(3);

    expect($user->can('view', $location))->toBeTrue();

    $otherTenantLocation = Location::query()->withoutGlobalScopes()->getModel();
    $otherTenantLocation->forceFill([
        'location_id' => 99,
        'tenant_id' => 99,
        'name' => 'Other',
        'short_code' => 'OTH',
    ]);

    expect($user->can('view', $otherTenantLocation))->toBeFalse();
});

it('requires authentication for protected auth routes', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
