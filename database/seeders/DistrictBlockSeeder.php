<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\DistrictBlock;
use Illuminate\Database\Seeder;

class DistrictBlockSeeder extends Seeder
{
    public function run(): void
    {
        $byDistrictName = require config_path('cfa_blocks.php');

        foreach ($byDistrictName as $districtName => $blocks) {
            $districtId = District::query()->where('name', $districtName)->value('id');
            if ($districtId === null) {
                continue;
            }

            foreach (array_values($blocks) as $i => $name) {
                DistrictBlock::query()->updateOrCreate(
                    [
                        'district_id' => $districtId,
                        'name' => $name,
                    ],
                    [
                        'sort_order' => $i,
                    ]
                );
            }
        }
    }
}
