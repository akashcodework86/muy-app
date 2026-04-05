<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_deliverable_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('deliverables')->cascadeOnDelete();
            $table->unsignedInteger('target_total');
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'district_id', 'deliverable_id'], 'fy_dist_deliv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_deliverable_targets');
    }
};
