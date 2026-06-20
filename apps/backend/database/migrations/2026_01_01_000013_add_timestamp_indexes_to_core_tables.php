<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->index('created_at', 'idx_tenants_created_at');
            $table->index('updated_at', 'idx_tenants_updated_at');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->index('created_at', 'idx_locations_created_at');
            $table->index('updated_at', 'idx_locations_updated_at');
        });

        Schema::table('cutoff_events', function (Blueprint $table) {
            $table->index('created_at', 'idx_cutoff_events_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('cutoff_events', function (Blueprint $table) {
            $table->dropIndex('idx_cutoff_events_created_at');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('idx_locations_created_at');
            $table->dropIndex('idx_locations_updated_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('idx_tenants_created_at');
            $table->dropIndex('idx_tenants_updated_at');
        });
    }
};
