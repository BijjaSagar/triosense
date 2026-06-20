<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cutoff_events', function (Blueprint $table) {
            $table->bigIncrements('cutoff_event_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamp('decided_at', 3);
            $table->enum('mode', ['shadow', 'live']);
            $table->enum('previous_status', ['open', 'approaching_cutoff', 'cutoff_declared', 'closed'])->nullable();
            $table->enum('new_status', ['open', 'approaching_cutoff', 'cutoff_declared', 'closed']);
            $table->unsignedInteger('queue_head');
            $table->unsignedInteger('queue_tail');
            $table->unsignedInteger('tokens_remaining');
            $table->unsignedInteger('cutoff_position')->nullable();
            $table->decimal('issuance_rate', 8, 3)->nullable();
            $table->decimal('arrival_rate', 8, 3)->nullable();
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['location_id', 'decided_at'], 'idx_ce_location_time');
            $table->index(['tenant_id', 'decided_at'], 'idx_ce_tenant_time');
            $table->index('new_status', 'idx_ce_status');

            $table->foreign('location_id', 'fk_ce_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
            $table->foreign('tenant_id', 'fk_ce_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutoff_events');
    }
};
