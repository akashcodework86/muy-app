<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pitch_deck_preparations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('cfa_submission_id')->nullable();
            $table->unsignedBigInteger('legacy_application_id')->nullable();

            $table->unsignedBigInteger('district_id');
            $table->string('incubatee_name');
            $table->string('application_no', 64)->nullable();

            $table->unsignedBigInteger('entered_by_user_id');
            $table->string('entered_by_name');

            $table->date('prepared_on');
            $table->string('prepared_for', 191)->nullable();
            $table->string('support_mode', 16)->nullable();
            $table->text('remarks')->nullable();

            $table->string('deck_file_disk', 32)->default('local');
            $table->string('deck_file_path');
            $table->string('deck_file_name');

            $table->timestamps();

            $table->unique('cfa_submission_id', 'pdp_cfa_submission_unique');
            $table->unique('legacy_application_id', 'pdp_legacy_application_unique');
            $table->index('district_id', 'pdp_district_idx');
            $table->index('prepared_on', 'pdp_prepared_on_idx');
            $table->index('entered_by_user_id', 'pdp_entered_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pitch_deck_preparations');
    }
};
