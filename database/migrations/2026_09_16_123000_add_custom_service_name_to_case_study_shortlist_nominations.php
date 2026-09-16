<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_study_shortlist_nominations')
            && ! Schema::hasColumn('case_study_shortlist_nominations', 'custom_service_name')) {
            Schema::table('case_study_shortlist_nominations', function (Blueprint $table): void {
                $table->string('custom_service_name')->nullable()->after('service_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('case_study_shortlist_nominations')
            && Schema::hasColumn('case_study_shortlist_nominations', 'custom_service_name')) {
            Schema::table('case_study_shortlist_nominations', function (Blueprint $table): void {
                $table->dropColumn('custom_service_name');
            });
        }
    }
};
