<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edge_devices', function (Blueprint $table) {
            $table->bigIncrements('edge_device_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->string('device_uid', 80);
            $table->string('hardware_id', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('firmware_version', 40)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->enum('status', ['online', 'degraded', 'offline', 'retired'])->default('offline');
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->unique('device_uid', 'uq_edge_devices_uid');
            $table->index('tenant_id', 'idx_edge_devices_tenant');
            $table->index('location_id', 'idx_edge_devices_location');
            $table->index('last_heartbeat_at', 'idx_edge_devices_heartbeat');
            $table->index('created_at', 'idx_edge_devices_created_at');
            $table->index('updated_at', 'idx_edge_devices_updated_at');

            $table->foreign('tenant_id', 'fk_edge_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_edge_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_devices');
    }
};
