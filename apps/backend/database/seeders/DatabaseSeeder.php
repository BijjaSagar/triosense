<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tenants')->insertOrIgnore([
            [
                'tenant_id'     => 1,
                'name'          => 'Tirumala Tirupati Devasthanams',
                'slug'          => 'ttd',
                'contact_email' => 'ops@ttd.gov.in',
                'timezone'      => 'Asia/Kolkata',
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        DB::table('locations')->insertOrIgnore([
            [
                'location_id'   => 1,
                'tenant_id'     => 1,
                'name'          => 'Vishnu Nivasam',
                'short_code'    => 'VSN',
                'address'       => 'Opposite Tirupati Railway Station, Tirupati, AP 517501',
                'latitude'      => 13.6315000,
                'longitude'     => 79.4192000,
                'opens_at'      => '05:00:00',
                'closes_at'     => '12:00:00',
                'default_quota' => 5000,
                'mode'          => 'shadow',
                'festival_mode' => false,
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'location_id'   => 2,
                'tenant_id'     => 1,
                'name'          => 'Srinivasam Complex',
                'short_code'    => 'SRN',
                'address'       => 'Opposite Central Bus Stand, Tirupati, AP 517501',
                'latitude'      => 13.6398000,
                'longitude'     => 79.4147000,
                'opens_at'      => '05:00:00',
                'closes_at'     => '12:00:00',
                'default_quota' => 5000,
                'mode'          => 'shadow',
                'festival_mode' => false,
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'location_id'   => 3,
                'tenant_id'     => 1,
                'name'          => 'Bhudevi Complex',
                'short_code'    => 'BDV',
                'address'       => 'Bhudevi Complex, Tirupati, AP 517501',
                'latitude'      => 13.6480000,
                'longitude'     => 79.4200000,
                'opens_at'      => '05:00:00',
                'closes_at'     => '12:00:00',
                'default_quota' => 5000,
                'mode'          => 'shadow',
                'festival_mode' => false,
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);
    }
}
