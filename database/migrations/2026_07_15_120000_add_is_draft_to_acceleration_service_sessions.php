<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            return;
        }

        Schema::table('acceleration_service_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                $table->boolean('is_draft')->default(false)->after('counts_for_7_2');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            return;
        }

        Schema::table('acceleration_service_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }
};
