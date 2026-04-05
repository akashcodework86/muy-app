<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_submissions', function (Blueprint $table) {
            $table->string('application_no', 40)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('cfa_submissions', function (Blueprint $table) {
            $table->dropUnique(['application_no']);
            $table->dropColumn('application_no');
        });
    }
};
