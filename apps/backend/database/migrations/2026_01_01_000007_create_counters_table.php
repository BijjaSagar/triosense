<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->bigIncrements('counter_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->string('name', 60);
            $table->string('short_code', 20);
            $table->enum('status', ['active', 'closed', 'maintenance'])->default('active');
            $table->timestamps();

            $table->unique(['location_id', 'short_code'], 'uq_counters_location_code');
            $table->index('tenant_id', 'idx_counters_tenant');
            $table->index('location_id', 'idx_counters_location');
            $table->index('created_at', 'idx_counters_created_at');
            $table->index('updated_at', 'idx_counters_updated_at');

            $table->foreign('tenant_id', 'fk_counters_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_counters_location')
                ->references('location_id')->on('locations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};
