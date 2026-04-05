<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HubSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('hubs')->insert([
            ['slug' => 'kumaon', 'name' => 'Kumaon Region (Eastern Uttarakhand)', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'garhwal', 'name' => 'Garhwal Region (Western Uttarakhand)', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
