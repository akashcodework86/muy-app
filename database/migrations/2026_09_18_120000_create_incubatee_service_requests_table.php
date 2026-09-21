<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incubatee_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('staff_note')->nullable();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cfa_submission_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incubatee_service_requests');
    }
};
