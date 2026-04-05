<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('referral_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant_name', 191);
            $table->string('phone', 20);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['referral_user_id', 'created_at']);
            $table->index(['district_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_submissions');
    }
};
