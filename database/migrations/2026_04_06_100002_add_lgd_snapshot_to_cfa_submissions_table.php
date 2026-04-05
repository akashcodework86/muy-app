<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_submissions', function (Blueprint $table) {
            $table->string('lgd_state_code', 8)->nullable()->after('district_id');
            $table->string('lgd_district_code', 16)->nullable()->after('lgd_state_code');
            $table->string('lgd_block_code', 16)->nullable()->after('lgd_district_code');
            $table->index(['lgd_district_code', 'lgd_block_code']);
        });
    }

    public function down(): void
    {
        Schema::table('cfa_submissions', function (Blueprint $table) {
            $table->dropIndex(['lgd_district_code', 'lgd_block_code']);
            $table->dropColumn(['lgd_state_code', 'lgd_district_code', 'lgd_block_code']);
        });
    }
};
