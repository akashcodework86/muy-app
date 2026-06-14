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
            if (! Schema::hasColumn('demo_days', 'event_photos_json')) {
                $table->json('event_photos_json')->nullable()->after('proof_file_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('demo_days')) {
            return;
        }

        Schema::table('demo_days', function (Blueprint $table): void {
            if (Schema::hasColumn('demo_days', 'event_photos_json')) {
                $table->dropColumn('event_photos_json');
            }
        });
    }
};
