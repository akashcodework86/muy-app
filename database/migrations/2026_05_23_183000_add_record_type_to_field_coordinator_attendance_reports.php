<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return;
        }

        if (! Schema::hasColumn('field_coordinator_attendance_reports', 'record_type')) {
            Schema::table('field_coordinator_attendance_reports', function (Blueprint $table): void {
                // Default 'block_workshop' so every new INSERT gets the right type automatically.
                $table->string('record_type', 32)->default('block_workshop')->after('status');
            });
        }

        // Every row that already exists was a "field visit" submitted before the block-workshop
        // feature was introduced. Flip them all so the new admin dashboard only shows the
        // genuinely new block-workshop submissions.
        DB::table('field_coordinator_attendance_reports')
            ->update(['record_type' => 'field_visit']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('field_coordinator_attendance_reports', 'record_type')) {
            Schema::table('field_coordinator_attendance_reports', function (Blueprint $table): void {
                $table->dropColumn('record_type');
            });
        }
    }
};
