<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->string('attendance_sheet_path')->nullable();
            $table->string('attendance_sheet_original_name')->nullable();
            $table->string('attendance_sheet_mime', 191)->nullable();
            $table->unsignedBigInteger('attendance_sheet_size_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_sheet_path',
                'attendance_sheet_original_name',
                'attendance_sheet_mime',
                'attendance_sheet_size_bytes',
            ]);
        });
    }
};
