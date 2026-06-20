<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('queue_events', function (Blueprint $table) {
            $table->bigIncrements('queue_event_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('edge_device_id');
            $table->unsignedBigInteger('camera_id')->nullable();
            $table->enum('event_type', ['enter', 'exit', 'issue', 'reverse', 'reconcile']);
            $table->timestamp('occurred_at', 3);
            $table->timestamp('received_at', 3);
            $table->string('track_id', 60)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['edge_device_id', 'occurred_at', 'track_id', 'event_type'],
                'uq_qe_dedup'
            );
            $table->index(['location_id', 'occurred_at'], 'idx_qe_location_time');
            $table->index(['tenant_id', 'occurred_at'], 'idx_qe_tenant_time');
            $table->index('event_type', 'idx_qe_type');
            $table->index('edge_device_id', 'idx_qe_edge');
            $table->index('created_at', 'idx_qe_created_at');

            $table->foreign('tenant_id', 'fk_qe_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_qe_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
            $table->foreign('edge_device_id', 'fk_qe_edge')
                ->references('edge_device_id')->on('edge_devices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_events');
    }
};
