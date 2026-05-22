<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_linkage_submission_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('market_linkage_submission_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['market_linkage_submission_id', 'created_at'], 'mlse_submission_created_idx');

            $table->foreign('market_linkage_submission_id', 'fk_mlse_submission')
                ->references('id')
                ->on('market_linkage_submissions')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'fk_mlse_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_linkage_submission_events');
    }
};
