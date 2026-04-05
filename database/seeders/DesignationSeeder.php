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
            ['MUY Spoke', 2],
            ['CDO', 3],
            ['District Staff', 4],
            ['Hub Coordinator', 5],
            ['Block / Field Staff', 6],
        ];

        foreach ($rows as [$name, $order]) {
            Designation::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $order]
            );
        }
    }
}
