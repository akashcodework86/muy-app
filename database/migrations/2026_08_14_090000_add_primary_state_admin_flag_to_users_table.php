<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_primary_state_admin')->default(false)->after('role');
        });

        // Every State Admin that existed before this feature is an established
        // primary authority. Newly created additional admins remain restricted.
        DB::table('users')
            ->where('role', 'state_admin')
            ->update(['is_primary_state_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_primary_state_admin');
        });
    }
};
