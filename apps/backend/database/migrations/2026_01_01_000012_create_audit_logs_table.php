<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('audit_log_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('action', 80);
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 400)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['tenant_id', 'occurred_at'], 'idx_audit_tenant_time');
            $table->index('user_id', 'idx_audit_user');
            $table->index('location_id', 'idx_audit_location');

            $table->foreign('tenant_id', 'fk_audit_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('user_id', 'fk_audit_user')
                ->references('user_id')->on('users')
                ->nullOnDelete();
            $table->foreign('location_id', 'fk_audit_location')
                ->references('location_id')->on('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
