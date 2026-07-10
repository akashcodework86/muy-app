<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acceleration_service_items')) {
            return;
        }

        Schema::table('acceleration_service_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('acceleration_service_items', 'payload')) {
                $table->json('payload')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acceleration_service_items')) {
            return;
        }

        Schema::table('acceleration_service_items', function (Blueprint $table): void {
            if (Schema::hasColumn('acceleration_service_items', 'payload')) {
                $table->dropColumn('payload');
            }
        });
    }
};
