<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eap_edp_sessions') && ! Schema::hasColumn('eap_edp_sessions', 'participants_json')) {
            Schema::table('eap_edp_sessions', function (Blueprint $table): void {
                $table->json('participants_json')->nullable()->after('attendance_total_count');
            });
        }

        if (Schema::hasTable('district_workshop_sessions') && ! Schema::hasColumn('district_workshop_sessions', 'participants_json')) {
            Schema::table('district_workshop_sessions', function (Blueprint $table): void {
                $table->unsignedInteger('participants_total')->default(0)->after('female_participants');
                $table->json('participants_json')->nullable()->after('participants_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('eap_edp_sessions') && Schema::hasColumn('eap_edp_sessions', 'participants_json')) {
            Schema::table('eap_edp_sessions', function (Blueprint $table): void {
                $table->dropColumn('participants_json');
            });
        }

        if (Schema::hasTable('district_workshop_sessions')) {
            Schema::table('district_workshop_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('district_workshop_sessions', 'participants_json')) {
                    $table->dropColumn('participants_json');
                }
                if (Schema::hasColumn('district_workshop_sessions', 'participants_total')) {
                    $table->dropColumn('participants_total');
                }
            });
        }
    }
};
