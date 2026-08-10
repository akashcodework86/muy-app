<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DELIVERABLE_CODE = 'media_campaigns';

    private const NEW_NAME = 'IEC & Promotional Activities for MUY';

    private const OLD_NAME = 'Newspaper Ads and Radio promotion campaigns';

    public function up(): void
    {
        if (Schema::hasTable('deliverables')) {
            DB::table('deliverables')
                ->where('code', self::DELIVERABLE_CODE)
                ->update([
                    'name' => self::NEW_NAME,
                    'mis_entry_label' => self::NEW_NAME,
                    'updated_at' => now(),
                ]);
        }

        $deliverableId = Schema::hasTable('deliverables')
            ? DB::table('deliverables')->where('code', self::DELIVERABLE_CODE)->value('id')
            : null;

        if (
            $deliverableId !== null
            && Schema::hasTable('services')
            && Schema::hasColumn('services', 'reporting_tier')
        ) {
            $serviceUpdates = [
                'reporting_tier' => 'non_key',
                'updated_at' => now(),
            ];

            DB::table('services')
                ->where('deliverable_id', $deliverableId)
                ->update($serviceUpdates);

            DB::table('services')
                ->where('deliverable_id', $deliverableId)
                ->where('name', self::OLD_NAME)
                ->update([
                    'name' => self::NEW_NAME,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('program_deliverable_row_metadata')) {
            $metadata = DB::table('program_deliverable_row_metadata')->where('serial', '10.4');

            if ($metadata->exists()) {
                $metadata->update([
                    'indicator_type' => 'Non-Key',
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('program_deliverable_row_metadata')->insert([
                    'serial' => '10.4',
                    'indicator_type' => 'Non-Key',
                    'level' => null,
                    'updated_by_user_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deliverables')) {
            DB::table('deliverables')
                ->where('code', self::DELIVERABLE_CODE)
                ->update([
                    'name' => self::OLD_NAME,
                    'mis_entry_label' => 'Newspaper / Radio campaigns',
                    'updated_at' => now(),
                ]);
        }

        $deliverableId = Schema::hasTable('deliverables')
            ? DB::table('deliverables')->where('code', self::DELIVERABLE_CODE)->value('id')
            : null;

        if (
            $deliverableId !== null
            && Schema::hasTable('services')
            && Schema::hasColumn('services', 'reporting_tier')
        ) {
            DB::table('services')
                ->where('deliverable_id', $deliverableId)
                ->update([
                    'reporting_tier' => 'key',
                    'updated_at' => now(),
                ]);

            DB::table('services')
                ->where('deliverable_id', $deliverableId)
                ->where('name', self::NEW_NAME)
                ->update([
                    'name' => self::OLD_NAME,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('program_deliverable_row_metadata')) {
            DB::table('program_deliverable_row_metadata')
                ->where('serial', '10.4')
                ->update([
                    'indicator_type' => 'Key Indicator',
                    'updated_at' => now(),
                ]);
        }
    }
};
