<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stakeholder_capacity_building_sessions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name');

            $table->date('session_date');
            $table->string('workshop_mode', 16)->default('physical');
            $table->string('venue', 191);
            $table->string('stakeholder_type', 32);
            $table->string('stakeholder_type_other', 191)->nullable();
            $table->string('department_name', 191)->nullable();
            $table->string('session_title');
            $table->text('topics_covered')->nullable();
            $table->unsignedInteger('staff_trained_total')->default(0);

            $table->json('attendance_media_json');
            $table->json('workshop_photos_json')->nullable();

            $table->timestamps();

            $table->index('submitted_by_user_id', 'scbs_submitted_by_idx');
            $table->index('session_date', 'scbs_session_date_idx');
            $table->index('stakeholder_type', 'scbs_stakeholder_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholder_capacity_building_sessions');
    }
};
