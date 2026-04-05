<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_application_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('block_name', 191);
            $table->unsignedInteger('last_serial')->default(0);
            $table->timestamps();

            $table->unique(['district_id', 'year', 'month', 'block_name'], 'cfa_app_seq_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_application_sequences');
    }
};
