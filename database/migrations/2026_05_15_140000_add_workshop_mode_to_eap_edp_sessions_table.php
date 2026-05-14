<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->string('workshop_mode', 16)->default('physical')->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->dropColumn('workshop_mode');
        });
    }
};
