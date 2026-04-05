<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('target_size');
            $table->string('status', 16)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->date('onboarding_date')->nullable();
            $table->timestamp('pdf_deadline_extended_until')->nullable();
            $table->boolean('pdf_compliance_waived')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hub_id', 'district_id', 'status']);
        });

        Schema::create('onboarding_batch_draft_cfa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_batch_id')->constrained('onboarding_batches')->cascadeOnDelete();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['onboarding_batch_id', 'cfa_submission_id'], 'uk_ob_draft_batch_cfa');
            $table->unique('cfa_submission_id', 'uk_ob_draft_cfa_once');
        });

        Schema::create('onboarding_batch_cfa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_batch_id')->constrained('onboarding_batches')->cascadeOnDelete();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['onboarding_batch_id', 'cfa_submission_id'], 'uk_ob_mem_batch_cfa');
            $table->unique('cfa_submission_id', 'uk_ob_mem_cfa_once');
        });

        Schema::create('cfa_hub_choice_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->string('state', 16)->default('open');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hub_id', 'cfa_submission_id'], 'uk_cfa_hub_choice_hub_cfa');
        });

        Schema::create('onboarding_batch_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_batch_id')->constrained('onboarding_batches')->cascadeOnDelete();
            $table->string('doc_type', 32)->default('cdo_signed');
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['onboarding_batch_id', 'doc_type'], 'uk_ob_doc_batch_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_batch_documents');
        Schema::dropIfExists('cfa_hub_choice_states');
        Schema::dropIfExists('onboarding_batch_cfa');
        Schema::dropIfExists('onboarding_batch_draft_cfa');
        Schema::dropIfExists('onboarding_batches');
    }
};
