<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_categories', 'target_mode')) {
                $table->string('target_mode', 24)
                    ->default('service')
                    ->after('sort_order');
                $table->index('target_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('service_categories', 'target_mode')) {
                $table->dropIndex(['target_mode']);
                $table->dropColumn('target_mode');
            }
        });
    }
};

