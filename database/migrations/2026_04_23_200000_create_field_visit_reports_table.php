<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('district_blocks')->nullOnDelete();
            $table->date('visit_date');
            $table->string('area', 191)->nullable();
            $table->unsignedSmallInteger('total_villages')->default(0);
            $table->text('village_names')->nullable();
            $table->unsignedSmallInteger('total_participants')->default(0);
            $table->unsignedSmallInteger('outreach_programmes')->default(0);
            $table->unsignedSmallInteger('cfas_reported')->default(0);
            $table->unsignedSmallInteger('cfas_verified')->nullable();
            $table->boolean('has_discrepancy')->default(false);
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'visit_date']);
            $table->index(['district_id', 'visit_date']);
            $table->index('visit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visit_reports');
    }
};
