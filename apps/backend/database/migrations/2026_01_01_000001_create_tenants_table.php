<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The multi-tenancy root.
 *
 * For TTD's pilot, exactly one tenant exists (TTD itself). The table
 * exists so the SaaS expansion path to other temple trusts is trivial.
 *
 * See ARCHITECTURE.md §4.1 and DATABASE_SCHEMA.md §1.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('tenant_id');
            $table->string('name', 120);
            $table->string('slug', 60);
            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('timezone', 60)->default('Asia/Kolkata');
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug', 'uq_tenants_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
