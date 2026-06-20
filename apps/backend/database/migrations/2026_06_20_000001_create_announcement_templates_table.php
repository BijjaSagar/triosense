<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_templates', function (Blueprint $table): void {
            $table->bigIncrements('template_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 60);
            $table->enum('language', ['te', 'ta', 'hi', 'en']);
            $table->text('text');
            $table->string('audio_file_path', 400)->nullable();
            $table->enum('status', ['draft', 'approved', 'retired'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code', 'language'], 'uq_template_code_lang');
            $table->index('tenant_id', 'idx_ann_templates_tenant');

            $table->foreign('tenant_id', 'fk_ann_templates_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_templates');
    }
};
