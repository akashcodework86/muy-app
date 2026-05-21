<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_linkage_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->unsignedBigInteger('district_id');
            $table->string('district_name', 191)->nullable();
            $table->unsignedBigInteger('cfa_submission_id')->nullable();
            $table->unsignedBigInteger('legacy_application_id')->nullable();
            $table->string('incubatee_name', 191);
            $table->string('application_no', 64)->nullable();
            $table->timestamps();

            $table->index(['district_id', 'created_at'], 'mls_district_created_idx');
            $table->index(['cfa_submission_id', 'created_at'], 'mls_cfa_created_idx');
            $table->index(['legacy_application_id', 'created_at'], 'mls_legacy_created_idx');
            $table->index('submitted_by_user_id', 'mls_submitter_idx');

            $table->foreign('submitted_by_user_id', 'fk_mls_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_mls_district')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
            $table->foreign('cfa_submission_id', 'fk_mls_cfa')
                ->references('id')
                ->on('cfa_submissions')
                ->nullOnDelete();
        });

        Schema::create('market_linkage_partners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('market_linkage_submission_id');
            $table->string('partner_name', 191);
            $table->string('linkage_mode', 16);
            $table->date('linkage_date');
            $table->string('document_disk', 32)->nullable();
            $table->string('document_path', 512)->nullable();
            $table->string('document_original_name', 191)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['market_linkage_submission_id', 'sort_order'], 'mlp_submission_sort_idx');
            $table->index('linkage_date', 'mlp_linkage_date_idx');

            $table->foreign('market_linkage_submission_id', 'fk_mlp_submission')
                ->references('id')
                ->on('market_linkage_submissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_linkage_partners');
        Schema::dropIfExists('market_linkage_submissions');
    }
};
