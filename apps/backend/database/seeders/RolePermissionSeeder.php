<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tenantId = 1;
        setPermissionsTeamId($tenantId);

        $permissions = [
            'location.view',
            'location.manage',
            'location.override',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);
        }

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'ttd_admin',
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $adminRole->syncPermissions($permissions);

        $supervisorRole = Role::query()->firstOrCreate([
            'name' => 'location_supervisor',
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $supervisorRole->syncPermissions(['location.view', 'location.manage', 'location.override']);

        $viewerRole = Role::query()->firstOrCreate([
            'name' => 'location_viewer',
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        $viewerRole->syncPermissions(['location.view']);
    }
}
