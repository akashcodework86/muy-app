<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potential_lakhpati_technical_trainings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name');

            $table->date('session_date');
            $table->unsignedBigInteger('district_id');
            $table->string('district_name');

            $table->unsignedBigInteger('district_block_id')->nullable();
            $table->string('block')->nullable();
            $table->unsignedBigInteger('gram_panchayat_id')->nullable();
            $table->string('area')->nullable();

            $table->string('workshop_mode', 16)->default('physical');
            $table->string('requesting_agency_type', 64);
            $table->string('session_title');
            $table->text('session_brief')->nullable();

            $table->unsignedInteger('male_participants')->default(0);
            $table->unsignedInteger('female_participants')->default(0);
            $table->unsignedInteger('participants_total')->default(0);
            $table->json('participants_json')->nullable();
            $table->json('attendance_media_json')->nullable();

            $table->timestamps();

            $table->index('submitted_by_user_id', 'pltt_submitted_by_idx');
            $table->index('session_date', 'pltt_session_date_idx');
            $table->index('district_id', 'pltt_district_idx');
            $table->index('district_block_id', 'pltt_block_idx');
            $table->index('gram_panchayat_id', 'pltt_gp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potential_lakhpati_technical_trainings');
    }
};
