<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'reporting_tier')) {
                $table->string('reporting_tier', 16)->default('unset')->after('allows_multiple');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'reporting_tier')) {
                $table->dropColumn('reporting_tier');
            }
        });
    }
};
