<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_packages', function (Blueprint $table): void {
            $table->json('training_packages')->nullable()->after('training_package');
            $table->json('attendance_media_json')->nullable()->after('attendance_file_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('training_packages', function (Blueprint $table): void {
            $table->dropColumn(['training_packages', 'attendance_media_json']);
        });
    }
};
