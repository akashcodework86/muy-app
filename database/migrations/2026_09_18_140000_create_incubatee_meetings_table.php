<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incubatee_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('mode', 16);
            $table->string('meeting_link', 500)->nullable();
            $table->string('venue', 255)->nullable();
            $table->text('agenda')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incubatee_meetings');
    }
};
