<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('potential_lakhpati_technical_trainings')) {
            return;
        }

        if (! Schema::hasColumn('potential_lakhpati_technical_trainings', 'workshop_photos_json')) {
            Schema::table('potential_lakhpati_technical_trainings', function (Blueprint $table): void {
                $table->json('workshop_photos_json')->nullable()->after('attendance_media_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('potential_lakhpati_technical_trainings')
            && Schema::hasColumn('potential_lakhpati_technical_trainings', 'workshop_photos_json')) {
            Schema::table('potential_lakhpati_technical_trainings', function (Blueprint $table): void {
                $table->dropColumn('workshop_photos_json');
            });
        }
    }
};
