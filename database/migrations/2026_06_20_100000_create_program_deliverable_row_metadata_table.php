<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_deliverable_row_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 32)->unique();
            $table->string('indicator_type', 64)->nullable();
            $table->string('level', 64)->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_deliverable_row_metadata');
    }
};
