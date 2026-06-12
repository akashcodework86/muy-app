<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'counts_toward_reap_support')) {
                $table->boolean('counts_toward_reap_support')->default(false)->after('reporting_tier');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'counts_toward_reap_support')) {
                $table->dropColumn('counts_toward_reap_support');
            }
        });
    }
};
