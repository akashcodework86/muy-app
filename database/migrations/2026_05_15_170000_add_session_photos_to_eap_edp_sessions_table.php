<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->json('session_photos_json')->nullable()->after('attendance_media_json');
        });
    }

    public function down(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->dropColumn('session_photos_json');
        });
    }
};
