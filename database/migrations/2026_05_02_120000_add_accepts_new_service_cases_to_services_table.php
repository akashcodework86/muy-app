<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'accepts_new_service_cases')) {
                $table->boolean('accepts_new_service_cases')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'accepts_new_service_cases')) {
                $table->dropColumn('accepts_new_service_cases');
            }
        });
    }
};
