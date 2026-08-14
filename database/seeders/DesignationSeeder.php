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
            ['Executive Director', 2],
            ['State Staff (SPOC)', 3],
            ['MUY Spoke', 4],
            ['CDO', 5],
            ['District Staff', 6],
            ['Hub Coordinator', 7],
            ['Block / Field Staff', 8],
        ];

        foreach ($rows as [$name, $order]) {
            Designation::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $order]
            );
        }
    }
}
