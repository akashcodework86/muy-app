<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_state_monthly_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->unsignedInteger('target_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['fiscal_year_id', 'deliverable_id', 'month_number'],
                'official_state_fy_deliv_month_unique',
            );
        });

        Schema::create('official_district_monthly_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->unsignedInteger('target_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['fiscal_year_id', 'district_id', 'deliverable_id', 'month_number'],
                'official_dist_fy_deliv_month_unique',
            );
        });

        Schema::create('official_hub_monthly_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('hub_id')->constrained('hubs')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->unsignedInteger('target_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['fiscal_year_id', 'hub_id', 'deliverable_id', 'month_number'],
                'official_hub_fy_deliv_month_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_hub_monthly_targets');
        Schema::dropIfExists('official_district_monthly_targets');
        Schema::dropIfExists('official_state_monthly_targets');
    }
};
