<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps each district to exactly one State Staff (SPOC) user.
 * - district_id is UNIQUE: a district can only have one active SPOC at a time.
 * - A single SPOC (state_staff user) can cover many districts (one row per district).
 * - assigned_by tracks which state admin made the assignment for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('district_service_spocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')
                ->unique()
                ->constrained('districts')
                ->cascadeOnDelete();
            $table->foreignId('state_staff_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->index('state_staff_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_service_spocs');
    }
};
