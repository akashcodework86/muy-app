<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eap_edp_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->date('event_date');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('district_name', 191)->nullable();
            $table->string('program_type', 32)->default('eap_edp');
            $table->string('topic', 191);
            $table->text('notes')->nullable();
            $table->json('attendance_media_json');
            $table->json('selected_incubatee_ids');
            $table->json('selected_incubatees_snapshot');
            $table->timestamps();

            $table->index(['submitted_by_user_id', 'event_date'], 'eap_edp_submitted_by_event_idx');
            $table->index(['district_id', 'event_date'], 'eap_edp_district_event_idx');
            $table->index(['program_type', 'event_date'], 'eap_edp_program_event_idx');

            $table->foreign('submitted_by_user_id', 'fk_eap_edp_session_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_eap_edp_session_district')
                ->references('id')
                ->on('districts')
                ->nullOnDelete();
        });

        if (Schema::hasTable('eap_edp_sessions')) {
            DB::table('eap_edp_sessions')
                ->whereIn('program_type', ['eap', 'edp'])
                ->update(['program_type' => 'eap_edp']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eap_edp_sessions');
    }
};
