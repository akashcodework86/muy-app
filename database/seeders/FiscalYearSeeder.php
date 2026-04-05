<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        FiscalYear::query()->updateOrCreate(
            ['code' => '2024-25'],
            [
                'name' => 'FY 2024-25',
                'starts_on' => '2024-04-02',
                'ends_on' => '2025-04-01',
                'is_active' => false,
            ]
        );

        FiscalYear::query()->updateOrCreate(
            ['code' => '2026-27'],
            [
                'name' => 'FY 2026-27',
                'starts_on' => '2026-04-02',
                'ends_on' => '2027-04-01',
                'is_active' => true,
            ]
        );
    }
}
