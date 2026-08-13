<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homestay_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 10)->unique();
            $table->string('phase', 20)->nullable();
            $table->unsignedBigInteger('source_id')->nullable()->comment('cfa_submissions.id or legacy application id');
            $table->string('application_no', 64)->nullable();
            $table->string('applicant_name')->nullable();
            $table->string('district')->nullable();
            $table->json('prefill_snapshot')->nullable();
            $table->json('answers');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['phase', 'district']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homestay_survey_responses');
    }
};
