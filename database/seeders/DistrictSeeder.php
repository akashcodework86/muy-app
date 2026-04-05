<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $kumaon = DB::table('hubs')->where('slug', 'kumaon')->value('id');
        $garhwal = DB::table('hubs')->where('slug', 'garhwal')->value('id');

        $rows = [
            [$kumaon, 'almora', 'Almora', 1],
            [$kumaon, 'bageshwar', 'Bageshwar', 2],
            [$kumaon, 'champawat', 'Champawat', 3],
            [$kumaon, 'nainital', 'Nainital', 4],
            [$kumaon, 'pithoragarh', 'Pithoragarh', 5],
            [$kumaon, 'udham-singh-nagar', 'Udham Singh Nagar', 6],
            [$garhwal, 'dehradun', 'Dehradun', 1],
            [$garhwal, 'haridwar', 'Haridwar', 2],
            [$garhwal, 'pauri-garhwal', 'Pauri Garhwal', 3],
            [$garhwal, 'tehri-garhwal', 'Tehri Garhwal', 4],
            [$garhwal, 'rudraprayag', 'Rudraprayag', 5],
            [$garhwal, 'chamoli', 'Chamoli', 6],
            [$garhwal, 'uttarkashi', 'Uttarkashi', 7],
        ];

        foreach ($rows as [$hubId, $slug, $name, $sort]) {
            DB::table('districts')->insert([
                'hub_id' => $hubId,
                'slug' => $slug,
                'name' => $name,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
