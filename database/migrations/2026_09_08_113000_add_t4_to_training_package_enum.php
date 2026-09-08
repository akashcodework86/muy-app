<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_packages') || ! Schema::hasColumn('training_packages', 'training_package')) {
            return;
        }

        // Legacy single-module column was ENUM(t1,t2,t3); app now allows t4.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `training_packages` MODIFY `training_package` ENUM('t1', 't2', 't3', 't4') NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_packages') || ! Schema::hasColumn('training_packages', 'training_package')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('training_packages')
                ->where('training_package', 't4')
                ->update(['training_package' => 't3']);

            DB::statement("ALTER TABLE `training_packages` MODIFY `training_package` ENUM('t1', 't2', 't3') NOT NULL");
        }
    }
};
