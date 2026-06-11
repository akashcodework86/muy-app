<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_monthly_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->unsignedInteger('target_count');
            $table->timestamps();

            $table->unique(
                ['fiscal_year_id', 'hub_id', 'deliverable_id', 'month_number'],
                'fy_hub_deliv_month_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_monthly_targets');
    }
};
