<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'tenant_id';

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table) use ($teamForeignKey, $teams) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'idx_permissions_tenant');
            }

            $table->unique(['name', 'guard_name', $teamForeignKey], 'uq_permissions_name_guard_tenant');
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teamForeignKey, $teams) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'idx_roles_tenant');
            }

            $table->unique(['name', 'guard_name', $teamForeignKey], 'uq_roles_name_guard_tenant');
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey, $teams) {
            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'idx_model_has_permissions_model');

            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'idx_model_has_permissions_tenant');
            }

            $table->foreign('permission_id', 'fk_model_has_permissions_permission')
                ->references('id')->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->primary(
                ['permission_id', $columnNames['model_morph_key'], 'model_type', $teamForeignKey],
                'pk_model_has_permissions'
            );
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $teamForeignKey, $teams) {
            $table->unsignedBigInteger('role_id');

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'idx_model_has_roles_model');

            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'idx_model_has_roles_tenant');
            }

            $table->foreign('role_id', 'fk_model_has_roles_role')
                ->references('id')->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary(
                ['role_id', $columnNames['model_morph_key'], 'model_type', $teamForeignKey],
                'pk_model_has_roles'
            );
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id', 'fk_role_has_permissions_permission')
                ->references('id')->on($tableNames['permissions'])
                ->cascadeOnDelete();
            $table->foreign('role_id', 'fk_role_has_permissions_role')
                ->references('id')->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'pk_role_has_permissions');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
