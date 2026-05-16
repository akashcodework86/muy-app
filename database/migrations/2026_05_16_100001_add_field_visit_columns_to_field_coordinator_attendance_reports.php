<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->foreignId('district_block_id')->nullable()->after('block')->constrained('district_blocks')->nullOnDelete();
            $table->foreignId('gram_panchayat_id')->nullable()->after('district_block_id')->constrained('gram_panchayats')->nullOnDelete();
            $table->text('remark')->nullable()->after('gram_panchayat_id');
            $table->json('visit_media_json')->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gram_panchayat_id');
            $table->dropConstrainedForeignId('district_block_id');
            $table->dropColumn(['remark', 'visit_media_json']);
        });
    }
};
