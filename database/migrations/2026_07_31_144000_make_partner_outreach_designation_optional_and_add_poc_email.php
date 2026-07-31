<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_partner_outreach_entries')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `marketing_partner_outreach_entries` MODIFY `partner_designation` VARCHAR(191) NULL');
            DB::statement('ALTER TABLE `marketing_partner_outreach_entries` MODIFY `poc_phone` VARCHAR(15) NULL');
        } elseif ($driver === 'sqlite') {
            // Fresh installs already get nullable columns from the create migration.
            // Existing sqlite DBs are uncommon in this project; skip MODIFY.
        }

        if (! Schema::hasColumn('marketing_partner_outreach_entries', 'poc_email')) {
            Schema::table('marketing_partner_outreach_entries', function (Blueprint $table): void {
                $table->string('poc_email', 191)->nullable()->after('poc_phone');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketing_partner_outreach_entries')) {
            return;
        }

        if (Schema::hasColumn('marketing_partner_outreach_entries', 'poc_email')) {
            Schema::table('marketing_partner_outreach_entries', function (Blueprint $table): void {
                $table->dropColumn('poc_email');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `marketing_partner_outreach_entries` MODIFY `partner_designation` VARCHAR(191) NOT NULL DEFAULT ''");
            DB::statement("ALTER TABLE `marketing_partner_outreach_entries` MODIFY `poc_phone` VARCHAR(15) NOT NULL DEFAULT ''");
        }
    }
};
