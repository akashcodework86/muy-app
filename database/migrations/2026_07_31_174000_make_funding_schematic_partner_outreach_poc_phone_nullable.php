<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funding_schematic_partner_outreach_entries')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `funding_schematic_partner_outreach_entries` MODIFY `poc_phone` VARCHAR(15) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('funding_schematic_partner_outreach_entries')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `funding_schematic_partner_outreach_entries` MODIFY `poc_phone` VARCHAR(15) NOT NULL DEFAULT ''");
        }
    }
};
