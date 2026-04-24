<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_batches', function (Blueprint $table): void {
            $table->timestamp('edit_unlocked_at')->nullable()->after('pdf_compliance_waived');
            $table->unsignedBigInteger('edit_unlocked_by_request_id')->nullable()->after('edit_unlocked_at');
        });

        Schema::create('onboarding_batch_edit_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('onboarding_batch_id');
            $table->unsignedBigInteger('hub_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('requested_by');
            $table->text('reason');
            $table->text('expected_changes');
            $table->string('status', 20)->default('pending'); // pending|approved
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('relocked_by')->nullable();
            $table->timestamp('relocked_at')->nullable();
            $table->timestamps();

            $table->index(['onboarding_batch_id', 'status']);
            $table->index(['hub_id', 'status']);
            $table->index(['requested_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_batch_edit_requests');

        Schema::table('onboarding_batches', function (Blueprint $table): void {
            $table->dropColumn(['edit_unlocked_at', 'edit_unlocked_by_request_id']);
        });
    }
};

