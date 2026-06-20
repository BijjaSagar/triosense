<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_location_assignments', function (Blueprint $table) {
            $table->bigIncrements('assignment_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('location_id');
            $table->boolean('can_override')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'location_id'], 'uq_user_location');
            $table->index('tenant_id', 'idx_ula_tenant');
            $table->index('location_id', 'idx_ula_location');

            $table->foreign('tenant_id', 'fk_ula_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('user_id', 'fk_ula_user')
                ->references('user_id')->on('users')
                ->cascadeOnDelete();
            $table->foreign('location_id', 'fk_ula_location')
                ->references('location_id')->on('locations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_location_assignments');
    }
};
