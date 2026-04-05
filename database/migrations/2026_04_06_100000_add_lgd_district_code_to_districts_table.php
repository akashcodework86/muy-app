<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->string('lgd_district_code', 16)->nullable()->after('sort_order');
            $table->index('lgd_district_code');
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropIndex(['lgd_district_code']);
            $table->dropColumn('lgd_district_code');
        });
    }
};
