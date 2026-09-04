<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('kind', 16)->default('individual');
            $table->dateTime('scheduled_at');
            $table->string('meeting_link', 500)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['district_id', 'status', 'scheduled_at']);
        });

        Schema::table('mentorship_requests', function (Blueprint $table) {
            $table->foreignId('mentorship_session_id')->nullable()->after('status')->constrained('mentorship_sessions')->nullOnDelete();
            $table->dateTime('done_at')->nullable()->after('mentorship_session_id');
            $table->dateTime('cancelled_at')->nullable()->after('done_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mentorship_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancelled_at', 'done_at']);
            $table->dropConstrainedForeignId('mentorship_session_id');
        });

        Schema::dropIfExists('mentorship_sessions');
    }
};
