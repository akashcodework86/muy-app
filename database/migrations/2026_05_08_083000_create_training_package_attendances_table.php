<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_package_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_taken_by_user_id');
            $table->string('event_taken_by_name', 191);
            $table->date('event_date');
            $table->unsignedBigInteger('district_id');
            $table->string('district_name', 191)->nullable();
            $table->string('block', 191);
            $table->string('training_package', 8);
            $table->string('attendance_file_path');
            $table->string('attendance_file_name', 191)->nullable();
            $table->string('attendance_file_mime', 191)->nullable();
            $table->unsignedBigInteger('attendance_file_size')->nullable();
            $table->json('selected_incubatees_json');
            $table->unsignedInteger('selected_incubatees_count')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['district_id', 'event_date'], 'tp_attendance_district_date_idx');
            $table->index(['event_taken_by_user_id', 'event_date'], 'tp_attendance_user_date_idx');
            $table->index(['training_package', 'event_date'], 'tp_attendance_package_date_idx');

            $table->foreign('event_taken_by_user_id', 'tp_attendance_event_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('district_id', 'tp_attendance_district_fk')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'tp_attendance_created_by_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('updated_by', 'tp_attendance_updated_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_package_attendances');
    }
};
