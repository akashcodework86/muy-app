<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('demo_days')) {
            return;
        }

        Schema::table('demo_days', function (Blueprint $table): void {
            if (! Schema::hasColumn('demo_days', 'participating_incubatees_json')) {
                $table->json('participating_incubatees_json')->nullable()->after('application_no');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('demo_days')) {
            return;
        }

        Schema::table('demo_days', function (Blueprint $table): void {
            if (Schema::hasColumn('demo_days', 'participating_incubatees_json')) {
                $table->dropColumn('participating_incubatees_json');
            }
        });
    }
};
