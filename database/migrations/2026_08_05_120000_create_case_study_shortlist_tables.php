<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('case_study_shortlists')) {
            Schema::create('case_study_shortlists', function (Blueprint $table): void {
                $table->id();
                $table->string('candidate_key', 96)->unique();
                $table->string('person_key', 96)->unique();
                $table->string('source', 24);
                $table->unsignedBigInteger('source_application_id');
                $table->string('program_year', 16);
                $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
                $table->date('shortlist_month');
                $table->string('applicant_name', 255);
                $table->string('application_no', 64)->nullable();
                $table->string('block_name', 128)->nullable();
                $table->string('business_category', 191)->nullable();
                $table->string('business_stage', 64)->nullable();
                $table->string('gender', 32)->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('removed_at')->nullable();
                $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('removal_reason')->nullable();
                $table->timestamps();

                $table->index(['district_id', 'shortlist_month', 'removed_at'], 'css_district_month_active_idx');
                $table->index(['program_year', 'shortlist_month'], 'css_year_month_idx');
                $table->unique(['source', 'source_application_id'], 'css_source_application_unique');
            });
        }

        if (! Schema::hasTable('case_study_shortlist_remarks')) {
            Schema::create('case_study_shortlist_remarks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('case_study_shortlist_id')->constrained('case_study_shortlists')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->string('author_role', 32);
                $table->text('remark');
                $table->timestamps();

                $table->index(['case_study_shortlist_id', 'created_at'], 'cssr_shortlist_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_study_shortlist_remarks');
        Schema::dropIfExists('case_study_shortlists');
    }
};
