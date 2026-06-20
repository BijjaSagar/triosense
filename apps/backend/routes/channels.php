<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('location.{locationId}', function (User $user, int $locationId): bool {
    Log::debug('Broadcast.channel.location', [
        'user_id' => $user->user_id,
        'location_id' => $locationId,
    ]);

    $location = Location::query()
        ->withoutGlobalScopes()
        ->where('location_id', $locationId)
        ->first();

    if ($location === null || (int) $user->tenant_id !== (int) $location->tenant_id) {
        return false;
    }

    setPermissionsTeamId($user->tenant_id);

    if ($user->hasRole('ttd_admin')) {
        return true;
    }

    return in_array($locationId, $user->assignedLocationIds(), true);
});
