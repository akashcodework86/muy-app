<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('district_workshop_sessions', function (Blueprint $table): void {
            $table->json('workshop_photos_json')->nullable()->after('attendance_media_json');
        });
    }

    public function down(): void
    {
        Schema::table('district_workshop_sessions', function (Blueprint $table): void {
            $table->dropColumn('workshop_photos_json');
        });
    }
};
