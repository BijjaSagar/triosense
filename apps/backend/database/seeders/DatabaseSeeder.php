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
        $this->seedOperatorUser();
        $this->seedDailyQuotas();
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
