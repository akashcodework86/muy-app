<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_service_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('source_phase', 16);
            $table->string('original_name');
            $table->string('normalized_name');
            $table->char('normalized_hash', 40);
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_phase', 'normalized_hash'], 'legacy_service_alias_phase_hash_unique');
            $table->index(['service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_service_aliases');
    }
};
