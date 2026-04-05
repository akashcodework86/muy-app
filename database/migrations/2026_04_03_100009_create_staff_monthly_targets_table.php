<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_monthly_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->unsignedInteger('target_count');
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'user_id', 'deliverable_id', 'month_number'], 'staff_month_deliv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_monthly_targets');
    }
};
