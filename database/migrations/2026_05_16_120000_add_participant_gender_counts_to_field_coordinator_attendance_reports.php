<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->unsignedInteger('participants_male_count')->default(0)->after('participants_total');
            $table->unsignedInteger('participants_female_count')->default(0)->after('participants_male_count');
        });
    }

    public function down(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->dropColumn(['participants_male_count', 'participants_female_count']);
        });
    }
};
