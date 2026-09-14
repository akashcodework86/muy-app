<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incubatee_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_batch_id')->constrained('onboarding_batches')->cascadeOnDelete();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->date('bill_date');
            $table->decimal('amount', 12, 2);
            $table->string('bill_number', 100)->unique();
            $table->string('document_disk', 32)->default('local');
            $table->string('document_path', 512);
            $table->string('document_original_name', 255)->nullable();
            $table->string('document_mime', 128)->nullable();
            $table->unsignedInteger('document_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['onboarding_batch_id', 'cfa_submission_id']);
            $table->index(['district_id', 'bill_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incubatee_bills');
    }
};
