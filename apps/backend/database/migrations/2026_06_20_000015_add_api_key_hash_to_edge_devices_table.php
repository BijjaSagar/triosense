<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('edge_devices', function (Blueprint $table): void {
            $table->string('api_key_hash', 255)->nullable()->after('config_json');
            $table->index('api_key_hash', 'idx_edge_devices_api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('edge_devices', function (Blueprint $table): void {
            $table->dropIndex('idx_edge_devices_api_key_hash');
            $table->dropColumn('api_key_hash');
        });
    }
};
