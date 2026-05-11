<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_packages')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('training_packages', function (Blueprint $table): void {
                $table->string('attendance_file_path')->nullable()->change();
                $table->string('attendance_file_name', 191)->nullable()->change();
                $table->string('attendance_file_mime', 191)->nullable()->change();
                $table->unsignedBigInteger('attendance_file_size_bytes')->nullable()->change();
            });

            return;
        }

        // Use raw SQL to avoid doctrine/dbal dependency on production hosts.
        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_path` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_name` VARCHAR(191) NULL');
        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_mime` VARCHAR(191) NULL');
        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_size_bytes` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_packages')) {
            return;
        }

        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_path` VARCHAR(255) NOT NULL');
        DB::statement("ALTER TABLE `training_packages` MODIFY `attendance_file_name` VARCHAR(191) NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE `training_packages` MODIFY `attendance_file_mime` VARCHAR(191) NOT NULL DEFAULT ''");
        DB::statement('ALTER TABLE `training_packages` MODIFY `attendance_file_size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
