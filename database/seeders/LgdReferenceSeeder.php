<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\DistrictBlock;
use Illuminate\Database\Seeder;

/**
 * Applies codes from config/cfa_lgd_reference.php (official LGD export for Uttarakhand).
 */
class LgdReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $ref = config('cfa_lgd_reference');

        foreach ($ref['district_by_slug'] ?? [] as $slug => $code) {
            if ($code === null || $code === '') {
                continue;
            }
            District::query()->where('slug', $slug)->update(['lgd_district_code' => (string) $code]);
        }

        foreach ($ref['blocks'] ?? [] as $districtSlug => $blocks) {
            $district = District::query()->where('slug', $districtSlug)->first();
            if ($district === null) {
                continue;
            }
            foreach ($blocks as $blockName => $lgd) {
                if ($lgd === null || $lgd === '') {
                    continue;
                }
                DistrictBlock::query()
                    ->where('district_id', $district->id)
                    ->where('name', $blockName)
                    ->update(['lgd_block_code' => (string) $lgd]);
            }
        }
    }
}
