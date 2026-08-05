<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('case_study_shortlist_nominations')) {
            Schema::create('case_study_shortlist_nominations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('case_study_shortlist_id')->constrained('case_study_shortlists')->cascadeOnDelete();
                $table->string('service_code', 64);
                $table->string('status', 32)->default('nominated');
                $table->text('nomination_note')->nullable();
                $table->foreignId('nominated_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('nominated_at');
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['case_study_shortlist_id', 'service_code'], 'cssn_shortlist_service_unique');
                $table->index(['service_code', 'status'], 'cssn_service_status_idx');
            });
        }

        if (! Schema::hasTable('case_study_shortlist_nomination_events')) {
            Schema::create('case_study_shortlist_nomination_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('case_study_shortlist_nomination_id')->constrained('case_study_shortlist_nominations')->cascadeOnDelete();
                $table->string('action', 32);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->text('note')->nullable();
                $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['case_study_shortlist_nomination_id', 'created_at'], 'cssne_nomination_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_study_shortlist_nomination_events');
        Schema::dropIfExists('case_study_shortlist_nominations');
    }
};
