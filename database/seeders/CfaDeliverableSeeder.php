<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Inserts only the MIS row for Call for Application (CFA).
 * Matches {@see DeliverableSeeder} row #3 so achievement code stays `cfa`.
 *
 * Run: php artisan db:seed --class=CfaDeliverableSeeder
 */
class CfaDeliverableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $payload = [
            'sort_order' => 3,
            'name' => 'Number of Call for Applications filled',
            'mis_entry_label' => 'Call for Application (CFA)',
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (DB::table('deliverables')->where('code', 'cfa')->exists()) {
            DB::table('deliverables')->where('code', 'cfa')->update($payload);

            return;
        }

        DB::table('deliverables')->insert(array_merge(
            ['code' => 'cfa', 'created_at' => $now],
            $payload
        ));
    }
}
