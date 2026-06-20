<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->bigIncrements('announcement_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->enum('trigger_type', ['automatic', 'manual']);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->text('text_played');
            $table->enum('language', ['te', 'ta', 'hi', 'en']);
            $table->unsignedBigInteger('cutoff_event_id')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->enum('status', ['queued', 'played', 'failed'])->default('queued');
            $table->string('failure_reason', 400)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['location_id', 'played_at'], 'idx_ann_location_time');
            $table->index('cutoff_event_id', 'idx_ann_cutoff');
            $table->index('tenant_id', 'idx_ann_tenant');

            $table->foreign('tenant_id', 'fk_ann_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
            $table->foreign('location_id', 'fk_ann_location')
                ->references('location_id')->on('locations')
                ->restrictOnDelete();
            $table->foreign('template_id', 'fk_ann_template')
                ->references('template_id')->on('announcement_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
