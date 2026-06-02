<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('state_tasks')) {
            Schema::create('state_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->unsignedInteger('target_value')->nullable();
                $table->string('status', 32)->default('draft');
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'due_date']);
            });
        }

        if (! Schema::hasTable('state_task_assignments')) {
            Schema::create('state_task_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_task_id')->constrained('state_tasks')->cascadeOnDelete();
                $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('pending');
                $table->unsignedInteger('progress_value')->default(0);
                $table->text('staff_note')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['state_task_id', 'assignee_user_id']);
                $table->index(['assignee_user_id', 'status']);
            });
        }

        if (! Schema::hasTable('state_task_attachments')) {
            Schema::create('state_task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_task_id')->constrained('state_tasks')->cascadeOnDelete();
                $table->string('disk', 32)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('state_task_progress_logs')) {
            Schema::create('state_task_progress_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_task_assignment_id')->constrained('state_task_assignments')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->unsignedInteger('progress_value')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('state_task_progress_logs');
        Schema::dropIfExists('state_task_attachments');
        Schema::dropIfExists('state_task_assignments');
        Schema::dropIfExists('state_tasks');
    }
};
