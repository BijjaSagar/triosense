<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('user_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('fcm_token', 255)->nullable();
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active');
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['tenant_id', 'email'], 'uq_users_tenant_email');
            $table->index('tenant_id', 'idx_users_tenant');
            $table->index('created_at', 'idx_users_created_at');
            $table->index('updated_at', 'idx_users_updated_at');

            $table->foreign('tenant_id', 'fk_users_tenant')
                ->references('tenant_id')->on('tenants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
