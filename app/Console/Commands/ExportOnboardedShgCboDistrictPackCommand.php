<?php

namespace App\Console\Commands;

use App\Services\Exports\OnboardedShgCboDistrictPackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportOnboardedShgCboDistrictPackCommand extends Command
{
    protected $signature = 'exports:onboarded-shg-cbo-district
        {--district=nainital : District slug or name}
        {--district-id= : District id (overrides --district)}
        {--path= : Absolute output path (optional)}';

    protected $description = 'Export onboarded SHG members + CBO counts/detail Excel for one district';

    public function handle(OnboardedShgCboDistrictPackService $pack): int
    {
        $districtId = $this->option('district-id') !== null && $this->option('district-id') !== ''
            ? (int) $this->option('district-id')
            : null;
        $district = (string) $this->option('district');

        $data = $pack->build($districtId, $districtId ? null : $district);
        $slug = (string) ($data['meta']['district_slug'] ?? 'all') ?: 'all';

        $relative = 'exports/onboarded-shg-cbo-'.$slug.'-'.now()->format('Ymd_His').'.xlsx';
        $absolute = $this->option('path')
            ? (string) $this->option('path')
            : Storage::disk('public')->path($relative);

        $pack->writeToPath($data, $absolute);

        $latestRel = 'exports/onboarded-shg-cbo-'.$slug.'-latest.xlsx';
        $latest = Storage::disk('public')->path($latestRel);
        @copy($absolute, $latest);

        $this->info('SHG onboarded: '.(int) ($data['shg']['count'] ?? 0));
        $this->info('CBO onboarded: '.(int) ($data['cbo']['count'] ?? 0));
        $this->info('Individual onboarded: '.(int) ($data['individual']['count'] ?? 0));
        $this->info('Phase 1 onboarded: '.(int) ($data['phase1']['count'] ?? 0));
        $this->info('Wrote: '.$absolute);
        $this->info('Latest: '.$latest);
        $this->info('Download: '.url('storage/'.$latestRel));
        $this->info('Admin (live): '.url('/admin/exports/onboarded-shg-cbo?district='.$slug));

        return self::SUCCESS;
    }
}
