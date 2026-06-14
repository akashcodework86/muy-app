<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stakeholder_consultation_workshops', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name');

            $table->date('workshop_date');
            $table->string('workshop_title');
            $table->string('workshop_mode', 16)->default('physical');
            $table->string('venue', 191);
            $table->string('organizing_level', 16);

            $table->unsignedBigInteger('hub_id')->nullable();
            $table->string('hub_name', 191)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('district_name', 191)->nullable();

            $table->json('primary_departments_json');
            $table->text('other_departments')->nullable();
            $table->json('stakeholder_types_json');
            $table->unsignedInteger('total_participants');
            $table->unsignedInteger('officials_count')->nullable();

            $table->text('consultation_theme');
            $table->text('key_outcomes');
            $table->string('mou_convergence_planned', 32)->nullable();

            $table->json('attendance_media_json');
            $table->json('workshop_photos_json')->nullable();
            $table->json('minutes_media_json')->nullable();

            $table->timestamps();

            $table->index('submitted_by_user_id', 'scw_submitted_by_idx');
            $table->index('workshop_date', 'scw_workshop_date_idx');
            $table->index('organizing_level', 'scw_organizing_level_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholder_consultation_workshops');
    }
};
