<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->text('venue_name_address')->nullable()->after('topic');
            $table->string('topic', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('eap_edp_sessions', function (Blueprint $table): void {
            $table->dropColumn('venue_name_address');
            $table->string('topic', 191)->nullable(false)->change();
        });
    }
};
