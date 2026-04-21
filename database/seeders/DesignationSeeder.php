<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
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

        foreach ($rows as [$name, $order]) {
            Designation::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $order]
            );
        }
    }
}
