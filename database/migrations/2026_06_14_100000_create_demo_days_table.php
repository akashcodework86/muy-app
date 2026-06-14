<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_days', function (Blueprint $table): void {
            $table->id();
            $table->date('event_date');
            $table->string('event_name', 255);
            $table->string('event_type', 32);
            $table->string('investor_name', 255)->nullable();
            $table->string('event_type_other', 191)->nullable();
            $table->string('venue', 255)->nullable();
            $table->string('mode', 32)->nullable();
            $table->unsignedInteger('male_participants')->default(0);
            $table->unsignedInteger('female_participants')->default(0);
            $table->unsignedBigInteger('cfa_submission_id')->nullable();
            $table->unsignedBigInteger('legacy_application_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('incubatee_name', 255);
            $table->string('application_no', 64)->nullable();
            $table->string('outcome', 32)->nullable();
            $table->text('summary')->nullable();
            $table->text('remarks')->nullable();
            $table->string('proof_file_disk', 32)->nullable();
            $table->string('proof_file_path', 512)->nullable();
            $table->string('proof_file_name', 255)->nullable();
            $table->unsignedBigInteger('entered_by_user_id');
            $table->string('entered_by_name', 191);
            $table->timestamps();

            $table->index('event_date', 'demo_days_event_date_idx');
            $table->index('entered_by_user_id', 'demo_days_entered_by_idx');
            $table->index('event_type', 'demo_days_event_type_idx');
            $table->index('cfa_submission_id', 'demo_days_cfa_idx');
            $table->index('legacy_application_id', 'demo_days_legacy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_days');
    }
};
