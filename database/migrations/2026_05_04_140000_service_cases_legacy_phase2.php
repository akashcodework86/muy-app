<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('service_cases', 'legacy_application_id')) {
                $table->unsignedBigInteger('legacy_application_id')->nullable()->after('cfa_submission_id');
                $table->index(['legacy_application_id', 'service_id'], 'service_cases_legacy_app_service_idx');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('service_cases', function (Blueprint $table) {
                $table->unsignedBigInteger('cfa_submission_id')->nullable()->change();
            });

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        Schema::table('service_cases', function (Blueprint $table) {
            $table->dropForeign(['cfa_submission_id']);
        });

        DB::statement('ALTER TABLE service_cases MODIFY cfa_submission_id BIGINT UNSIGNED NULL');

        Schema::table('service_cases', function (Blueprint $table) {
            $table->foreign('cfa_submission_id')
                ->references('id')
                ->on('cfa_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            if (Schema::hasColumn('service_cases', 'legacy_application_id')) {
                $table->dropIndex('service_cases_legacy_app_service_idx');
                $table->dropColumn('legacy_application_id');
            }
        });

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('service_cases')->whereNull('cfa_submission_id')->delete();

        Schema::table('service_cases', function (Blueprint $table) {
            $table->dropForeign(['cfa_submission_id']);
        });

        DB::statement('ALTER TABLE service_cases MODIFY cfa_submission_id BIGINT UNSIGNED NOT NULL');

        Schema::table('service_cases', function (Blueprint $table) {
            $table->foreign('cfa_submission_id')
                ->references('id')
                ->on('cfa_submissions')
                ->cascadeOnDelete();
        });
    }
};
