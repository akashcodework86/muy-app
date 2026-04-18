<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('service_categories')
                ->restrictOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['deliverable_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('deliverable_id')->nullable()->change();
            $table->boolean('allows_multiple')->default(false)->after('deliverable_id');
            $table->json('field_schema')->nullable()->after('allows_multiple');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('deliverable_id')
                ->references('id')
                ->on('deliverables')
                ->restrictOnDelete();
        });

        Schema::create('service_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_submission_id')->constrained('cfa_submissions')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('status', 24)->default('open');
            $table->json('payload')->nullable();
            $table->string('reference_number', 191)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cfa_submission_id', 'status']);
            $table->index(['cfa_submission_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_cases');

        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['deliverable_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['allows_multiple', 'field_schema']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('deliverable_id')->nullable(false)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('deliverable_id')
                ->references('id')
                ->on('deliverables')
                ->restrictOnDelete();
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
