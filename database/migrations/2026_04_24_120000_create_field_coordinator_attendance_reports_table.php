<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_coordinator_attendance_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('field_coordinator_user_id');
            $table->string('field_coordinator_name', 191);
            $table->date('visit_date');
            $table->date('entry_date');
            $table->string('area', 191)->nullable();
            $table->string('block', 191)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedInteger('villages_visited_total')->default(0);
            $table->json('villages_covered')->nullable();
            $table->unsignedInteger('participants_total')->default(0);
            $table->unsignedInteger('cfas_filled_total')->default(0);
            $table->unsignedInteger('outreach_programmes_total')->default(0);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime', 191)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->timestamps();

            $table->index(['field_coordinator_user_id', 'visit_date']);
            $table->index(['district_id', 'visit_date']);
            $table->foreign('field_coordinator_user_id', 'fk_fc_attendance_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_fc_attendance_district')
                ->references('id')
                ->on('districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_coordinator_attendance_reports');
    }
};
