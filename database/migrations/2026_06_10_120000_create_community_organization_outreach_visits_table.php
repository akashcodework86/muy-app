<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_organization_outreach_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hub_id');
            $table->string('hub_name', 191);
            $table->unsignedBigInteger('district_id');
            $table->string('district_name', 191);
            $table->date('visit_date');
            $table->string('organization_name', 255);
            $table->string('organization_type', 64);
            $table->string('person_met_name', 191);
            $table->string('person_met_designation', 191)->nullable();
            $table->string('poc_name', 191);
            $table->string('poc_phone', 15);
            $table->string('poc_email', 191)->nullable();
            $table->string('purpose', 64);
            $table->string('meeting_mode', 32)->default('physical');
            $table->string('outcome', 64)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index(['hub_id', 'visit_date'], 'coov_hub_visit_date_idx');
            $table->index(['district_id', 'visit_date'], 'coov_district_visit_date_idx');
            $table->index('visit_date', 'coov_visit_date_idx');

            $table->foreign('hub_id', 'fk_coov_hub')
                ->references('id')
                ->on('hubs')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_coov_district')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
            $table->foreign('submitted_by_user_id', 'fk_coov_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_organization_outreach_visits');
    }
};
