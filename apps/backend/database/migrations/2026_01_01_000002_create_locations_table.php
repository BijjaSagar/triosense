<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three TTD SSD counter locations:
 *   1. Vishnu Nivasam (opposite railway station)
 *   2. Srinivasam Complex (opposite central bus stand)
 *   3. Bhudevi Complex
 *
 * See DATABASE_SCHEMA.md §1.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->bigIncrements('location_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 120);
            $table->string('short_code', 20);
            $table->string('address', 400)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->time('opens_at')->default('05:00:00');
            $table->time('closes_at')->default('12:00:00');
            $table->unsignedInteger('default_quota')->default(5000);
            $table->enum('mode', ['shadow', 'live', 'disabled'])->default('shadow');
            $table->boolean('festival_mode')->default(false);
            $table->enum('status', ['active', 'maintenance', 'archived'])->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'short_code'], 'uq_locations_tenant_code');
            $table->index('tenant_id', 'idx_locations_tenant');

            $table->foreign('tenant_id', 'fk_locations_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
