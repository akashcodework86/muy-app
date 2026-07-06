<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_absence_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('absence_date');
            $table->text('reason');
            $table->timestamps();

            $table->unique(['user_id', 'absence_date']);
            $table->index('absence_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_absence_reasons');
    }
};
