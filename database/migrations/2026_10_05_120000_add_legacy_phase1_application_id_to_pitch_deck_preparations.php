<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pitch_deck_preparations', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_phase1_application_id')->nullable()->after('legacy_application_id');
            $table->unique('legacy_phase1_application_id', 'pdp_legacy_phase1_application_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pitch_deck_preparations', function (Blueprint $table): void {
            $table->dropUnique('pdp_legacy_phase1_application_unique');
            $table->dropColumn('legacy_phase1_application_id');
        });
    }
};
