<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64);
            $table->text('comment')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['cfa_submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_requests');
    }
};
