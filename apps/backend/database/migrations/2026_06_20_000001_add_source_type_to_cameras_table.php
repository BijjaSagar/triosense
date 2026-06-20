<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cameras', function (Blueprint $table): void {
            $table->enum('source_type', ['rtsp', 'webcam'])
                ->default('rtsp')
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table): void {
            $table->dropColumn('source_type');
        });
    }
};
