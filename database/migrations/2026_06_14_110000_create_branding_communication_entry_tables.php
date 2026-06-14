<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_study_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('story_title', 255);
            $table->string('story_type', 32);
            $table->unsignedBigInteger('cfa_submission_id')->nullable();
            $table->unsignedBigInteger('legacy_application_id')->nullable();
            $table->string('incubatee_key', 64)->nullable();
            $table->string('incubatee_name', 255);
            $table->string('application_no', 64)->nullable();
            $table->date('story_date');
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path', 512)->nullable();
            $table->string('document_original_name', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('story_date', 'cse_story_date_idx');
            $table->index('submitted_by_user_id', 'cse_submitter_idx');
            $table->index(['cfa_submission_id', 'legacy_application_id'], 'cse_incubatee_idx');

            $table->foreign('submitted_by_user_id', 'fk_cse_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('muy_newsletter_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('issue_date');
            $table->string('issue_edition', 128);
            $table->string('title', 255);
            $table->string('distribution_mode', 32);
            $table->string('newsletter_url', 2048)->nullable();
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path', 512)->nullable();
            $table->string('document_original_name', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('issue_date', 'mne_issue_date_idx');
            $table->index('submitted_by_user_id', 'mne_submitter_idx');

            $table->foreign('submitted_by_user_id', 'fk_mne_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('media_campaign_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('campaign_date');
            $table->string('media_type', 32);
            $table->string('channel_name', 255);
            $table->string('coverage_area', 191);
            $table->string('campaign_title', 255);
            $table->string('ad_size_or_duration', 128)->nullable();
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path', 512)->nullable();
            $table->string('document_original_name', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('campaign_date', 'mce_campaign_date_idx');
            $table->index('submitted_by_user_id', 'mce_submitter_idx');

            $table->foreign('submitted_by_user_id', 'fk_mce_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('media_campaign_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('media_campaign_entry_id');
            $table->string('attachment_type', 32);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('media_campaign_entry_id', 'mca_entry_idx');

            $table->foreign('media_campaign_entry_id', 'fk_mca_entry')
                ->references('id')
                ->on('media_campaign_entries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_campaign_attachments');
        Schema::dropIfExists('media_campaign_entries');
        Schema::dropIfExists('muy_newsletter_entries');
        Schema::dropIfExists('case_study_entries');
    }
};
