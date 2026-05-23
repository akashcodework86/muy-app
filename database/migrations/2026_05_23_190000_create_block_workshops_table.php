<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_workshops', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('field_coordinator_user_id')->nullable()->index();
            $table->string('field_coordinator_name')->nullable();

            $table->date('visit_date')->nullable();
            $table->date('entry_date')->nullable();

            $table->unsignedBigInteger('district_id')->nullable()->index();
            $table->unsignedBigInteger('district_block_id')->nullable()->index();
            $table->unsignedBigInteger('gram_panchayat_id')->nullable()->index();

            $table->string('block')->nullable();
            $table->string('area')->nullable();
            $table->text('remark')->nullable();

            $table->unsignedInteger('participants_male_count')->default(0);
            $table->unsignedInteger('participants_female_count')->default(0);
            $table->unsignedInteger('participants_total')->default(0);
            $table->json('participants_json')->nullable();

            $table->json('visit_media_json')->nullable();

            // Legacy single-file attachment (kept for future compat)
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();

            // Attendance sheet (Excel)
            $table->string('attendance_sheet_path')->nullable();
            $table->string('attendance_sheet_original_name')->nullable();
            $table->string('attendance_sheet_mime')->nullable();
            $table->unsignedBigInteger('attendance_sheet_size_bytes')->nullable();

            $table->string('status', 16)->default('submitted');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_workshops');
    }
};
