<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        Log::debug('LocationPolicy.viewAny', ['user_id' => $user->user_id]);

        return $this->isTenantAdmin($user) || $user->assignedLocationIds() !== [];
    }

    public function view(User $user, Location $location): bool
    {
        Log::debug('LocationPolicy.view', [
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
        ]);

        if ((int) $user->tenant_id !== (int) $location->tenant_id) {
            return false;
        }

        if ($this->isTenantAdmin($user)) {
            return true;
        }

        return in_array((int) $location->location_id, $user->assignedLocationIds(), true);
    }

    public function update(User $user, Location $location): bool
    {
        Log::debug('LocationPolicy.update', [
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
        ]);

        if ((int) $user->tenant_id !== (int) $location->tenant_id) {
            return false;
        }

        setPermissionsTeamId($user->tenant_id);

        if ($user->can('location.manage')) {
            return $this->view($user, $location);
        }

        return false;
    }

    private function isTenantAdmin(User $user): bool
    {
        setPermissionsTeamId($user->tenant_id);

        return $user->hasRole('ttd_admin');
    }
}
