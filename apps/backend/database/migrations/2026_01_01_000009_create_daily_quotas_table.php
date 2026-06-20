<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_quotas', function (Blueprint $table) {
            $table->bigIncrements('daily_quota_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->date('quota_date');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('issued')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->enum('closed_reason', ['quota_exhausted', 'operator_override', 'time_window', 'system_fault'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'quota_date'], 'uq_quota_location_date');
            $table->index(['tenant_id', 'quota_date'], 'idx_quota_tenant_date');
            $table->index('created_at', 'idx_daily_quotas_created_at');
            $table->index('updated_at', 'idx_daily_quotas_updated_at');

            $table->foreign('tenant_id', 'fk_quota_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_quota_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quotas');
    }
};
