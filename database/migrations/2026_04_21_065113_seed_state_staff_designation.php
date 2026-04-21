<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotently inserts the "State Staff (SPOC)" designation so existing
 * environments pick it up without re-running the full seeder.
 * Also bumps sort_order on the legacy rows below it to keep UI ordering
 * consistent with DesignationSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['State Admin', 1],
            ['State Staff (SPOC)', 2],
            ['MUY Spoke', 3],
            ['CDO', 4],
            ['District Staff', 5],
            ['Hub Coordinator', 6],
            ['Block / Field Staff', 7],
        ];

        $now = now();

        foreach ($rows as [$name, $order]) {
            $existingId = DB::table('designations')->where('name', $name)->value('id');
            if ($existingId) {
                DB::table('designations')
                    ->where('id', $existingId)
                    ->update([
                        'sort_order' => $order,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('designations')->insert([
                    'name' => $name,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('designations')->where('name', 'State Staff (SPOC)')->delete();
    }
};
