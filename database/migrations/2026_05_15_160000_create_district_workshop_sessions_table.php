<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_workshop_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->date('event_date');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('district_name', 191)->nullable();
            $table->string('topic', 191);
            $table->string('workshop_mode', 16)->default('physical');
            $table->text('notes')->nullable();
            $table->json('attendance_media_json');
            $table->json('selected_incubatee_ids');
            $table->json('selected_incubatees_snapshot');
            $table->timestamps();

            $table->index(['submitted_by_user_id', 'event_date'], 'dws_submitted_by_event_idx');
            $table->index(['district_id', 'event_date'], 'dws_district_event_idx');

            $table->foreign('submitted_by_user_id', 'fk_dws_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_dws_district')
                ->references('id')
                ->on('districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_workshop_sessions');
    }
};
