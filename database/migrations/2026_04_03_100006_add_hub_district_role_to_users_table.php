<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 48)->nullable()->after('password');
            $table->foreignId('hub_id')->nullable()->after('role')->constrained('hubs')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('hub_id')->constrained('districts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('hub_id');
            $table->dropColumn('role');
        });
    }
};
