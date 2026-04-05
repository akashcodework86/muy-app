<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('lgd_block_code', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['district_id', 'name']);
            $table->index(['district_id', 'lgd_block_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_blocks');
    }
};
