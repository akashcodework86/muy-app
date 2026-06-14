<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_department_meetings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name');

            $table->date('meeting_date');
            $table->string('meeting_level', 16);
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->string('hub_name', 191)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('district_name', 191)->nullable();

            $table->string('meeting_mode', 16);
            $table->string('venue', 191)->nullable();
            $table->string('department_name', 191);
            $table->string('department_unit', 191)->nullable();
            $table->string('official_name', 191);
            $table->string('official_designation', 191);
            $table->string('official_phone', 16)->nullable();
            $table->text('muy_staff_present');
            $table->string('meeting_purpose', 32);
            $table->string('meeting_purpose_other', 191)->nullable();
            $table->text('agenda_summary');
            $table->text('outcome_decision');
            $table->json('incubatees_discussed_json')->nullable();

            $table->json('proof_media_json');
            $table->json('photos_json')->nullable();

            $table->timestamps();

            $table->index('submitted_by_user_id', 'ldm_submitted_by_idx');
            $table->index('meeting_date', 'ldm_meeting_date_idx');
            $table->index('meeting_level', 'ldm_meeting_level_idx');
            $table->index('hub_id', 'ldm_hub_id_idx');
            $table->index('district_id', 'ldm_district_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_department_meetings');
    }
};
