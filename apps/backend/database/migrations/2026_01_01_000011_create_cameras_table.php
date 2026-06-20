<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->bigIncrements('camera_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('edge_device_id');
            $table->string('name', 80);
            $table->enum('role', ['entry_tripwire', 'counter_window', 'density', 'overview']);
            $table->string('rtsp_url', 400);
            $table->json('tripwire_json')->nullable();
            $table->enum('status', ['active', 'degraded', 'disabled'])->default('active');
            $table->timestamp('last_frame_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'idx_cameras_tenant');
            $table->index('location_id', 'idx_cameras_location');
            $table->index('edge_device_id', 'idx_cameras_edge');
            $table->index('created_at', 'idx_cameras_created_at');
            $table->index('updated_at', 'idx_cameras_updated_at');

            $table->foreign('tenant_id', 'fk_cameras_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_cameras_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
            $table->foreign('edge_device_id', 'fk_cameras_edge')
                ->references('edge_device_id')->on('edge_devices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
