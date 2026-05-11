<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_packages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->date('event_date');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('district_name', 191)->nullable();
            $table->string('block', 191)->nullable();
            $table->string('training_batch_name', 191)->nullable();
            $table->enum('training_package', ['t1', 't2', 't3']);
            $table->string('attendance_file_path');
            $table->string('attendance_file_name', 191);
            $table->string('attendance_file_mime', 191)->nullable();
            $table->unsignedBigInteger('attendance_file_size_bytes')->nullable();
            $table->json('selected_incubatee_ids');
            $table->json('selected_incubatees_snapshot');
            $table->timestamps();

            $table->index(['submitted_by_user_id', 'event_date'], 'tp_submitted_by_event_idx');
            $table->index(['district_id', 'event_date'], 'tp_district_event_idx');
            $table->index(['training_package', 'event_date'], 'tp_package_event_idx');

            $table->foreign('submitted_by_user_id', 'fk_training_package_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'fk_training_package_district')
                ->references('id')
                ->on('districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_packages');
    }
};
