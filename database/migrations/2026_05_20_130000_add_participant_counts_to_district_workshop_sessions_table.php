<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('district_workshop_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('male_participants')->default(0)->after('notes');
            $table->unsignedInteger('female_participants')->default(0)->after('male_participants');
            $table->string('topic', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('district_workshop_sessions', function (Blueprint $table): void {
            $table->dropColumn(['male_participants', 'female_participants']);
            $table->string('topic', 191)->nullable(false)->change();
        });
    }
};
