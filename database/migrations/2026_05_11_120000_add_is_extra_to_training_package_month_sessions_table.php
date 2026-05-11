<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_package_month_sessions', function (Blueprint $table): void {
            $table->boolean('is_extra')->default(false)->after('session_name');
        });
    }

    public function down(): void
    {
        Schema::table('training_package_month_sessions', function (Blueprint $table): void {
            $table->dropColumn('is_extra');
        });
    }
};
