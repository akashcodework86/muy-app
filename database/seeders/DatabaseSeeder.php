<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            HubSeeder::class,
            DistrictSeeder::class,
            DistrictBlockSeeder::class,
            LgdReferenceSeeder::class,
            FiscalYearSeeder::class,
            DeliverableSeeder::class,
            ServiceCategorySeeder::class,
            ServiceSeeder::class,
            DesignationSeeder::class,
            DemoTargetSeeder::class,
        ]);
    }
}
