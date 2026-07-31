<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_partner_outreach_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('outreach_date');
            $table->string('partner_name', 255);
            $table->string('partner_designation', 191)->nullable();
            $table->string('partner_link', 2048)->nullable();
            $table->string('cohort_or_sector', 64);
            $table->string('cohort_or_sector_other', 191)->nullable();
            $table->string('poc_name', 191)->nullable();
            $table->string('poc_phone', 15)->nullable();
            $table->string('poc_email', 191)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 32)->default('outreach');
            $table->date('onboarding_date')->nullable();
            $table->string('agreement_document_disk', 32)->nullable();
            $table->string('agreement_document_path', 512)->nullable();
            $table->string('agreement_document_original_name', 255)->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->unsignedBigInteger('status_updated_by_user_id')->nullable();
            $table->string('status_updated_by_name', 191)->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();

            $table->index(['outreach_date', 'status'], 'mpoe_outreach_status_idx');
            $table->index('status', 'mpoe_status_idx');
            $table->index('submitted_by_user_id', 'mpoe_submitter_idx');

            $table->foreign('submitted_by_user_id', 'fk_mpoe_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('status_updated_by_user_id', 'fk_mpoe_status_updater')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_partner_outreach_entries');
    }
};
