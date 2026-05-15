<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('attendance_male_count')->default(0)->after('workshop_mode');
            $table->unsignedInteger('attendance_female_count')->default(0)->after('attendance_male_count');
            $table->unsignedInteger('attendance_total_count')->default(0)->after('attendance_female_count');
        });
    }

    public function down(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_male_count',
                'attendance_female_count',
                'attendance_total_count',
            ]);
        });
    }
};
