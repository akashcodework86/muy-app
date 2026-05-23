<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->string('status', 16)->default('submitted')->after('remark');
            $table->json('participants_json')->nullable()->after('status');
        });

        if (Schema::hasColumn('field_coordinator_attendance_reports', 'status')) {
            DB::table('field_coordinator_attendance_reports')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'submitted']);
        }
    }

    public function down(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->dropColumn(['status', 'participants_json']);
        });
    }
};
