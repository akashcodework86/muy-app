<?php

namespace App\Services\ReviewPpt;

use App\Models\Deliverable;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\OfficialDistrictMonthlyTarget;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\ProgramDeliverablesReportService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReviewPptDataService
{
    public function __construct(private readonly ProgramDeliverablesReportService $reportService) {}

    /** @return array{districts: array<string, string>, targets: array<string, array<string, int>>, achievements: array<string, array<string, int>>} */
    public function build(FiscalYear $fiscalYear, ReviewPptSelection $selection): array
    {
        $slugs = $selection->districtSlugs;
        $districts = District::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
        if ($districts->count() !== count($slugs)) {
            throw new RuntimeException('One or more selected Uttarakhand districts are missing.');
        }

        $blocks = collect(config('official_district_monthly_targets.district_blocks', []))
            ->filter(fn ($block) => is_array($block) && isset($block['mis_serial']))
            ->keyBy('mis_serial');
        $serials = array_merge(config('review_ppt.key_indicators', []), config('review_ppt.non_key_indicators', []));
        $saved = $this->savedTargets((int) $fiscalYear->id, $serials);
        $targets = [];
        $achievements = [];
        $names = [];
        $scope = new ProgramDeliverablesScope('state_admin', null, null, true);

        foreach ($slugs as $slug) {
            $district = $districts->get($slug);
            $districtId = (int) $district->id;
            $names[$slug] = (string) $district->name;
            $filter = new ProgramDeliverablesFilter(
                fiscalYearId: (int) $fiscalYear->id,
                districtId: $districtId,
                month: null,
                year: null,
                dateFrom: $selection->achievementFrom->toDateString(),
                dateTo: $selection->periodTo->toDateString(),
            );
            $report = $this->reportService->build($filter, $scope, attachCompanionColumns: false);
            $reportRows = collect($report['rows'])->keyBy('serial');

            foreach ($serials as $serial) {
                $block = $blocks->get($serial);
                $months = is_array($block['districts'][$slug] ?? null) ? array_values($block['districts'][$slug]) : [];
                $target = 0;
                for ($month = $selection->targetFromMonth; $month <= $selection->targetToMonth; $month++) {
                    $target += $saved[$serial][$districtId][$month]
                        ?? max(0, (int) ($months[$month - 1] ?? 0));
                }
                $targets[$slug][$serial] = $target;
                $achievements[$slug][$serial] = max(0, (int) ($reportRows->get($serial)['achievement'] ?? 0));
            }
        }

        return ['districts' => $names, 'targets' => $targets, 'achievements' => $achievements,
            'selection' => $selection];
    }

    /** @param list<string> $serials
     *  @return array<string, array<int, array<int, int>>>
     */
    private function savedTargets(int $fiscalYearId, array $serials): array
    {
        if (! Schema::hasTable('official_district_monthly_targets')) {
            return [];
        }

        $codes = config('official_monthly_target_serial_codes', []);
        $codeBySerial = [];
        foreach ($serials as $serial) {
            $code = $codes[$serial] ?? $this->matrixSourceCode($serial);
            if (is_string($code) && $code !== '') {
                $codeBySerial[$serial] = $code;
            }
        }
        $ids = Deliverable::query()->whereIn('code', array_values($codeBySerial))->pluck('id', 'code');
        $serialById = [];
        foreach ($codeBySerial as $serial => $code) {
            if ($ids->has($code)) {
                $serialById[(int) $ids->get($code)] = $serial;
            }
        }
        if ($serialById === []) {
            return [];
        }

        $saved = [];
        foreach (OfficialDistrictMonthlyTarget::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereIn('deliverable_id', array_keys($serialById))
            ->get(['deliverable_id', 'district_id', 'month_number', 'target_count']) as $row) {
            $serial = $serialById[(int) $row->deliverable_id];
            $saved[$serial][(int) $row->district_id][(int) $row->month_number] = max(0, (int) $row->target_count);
        }

        return $saved;
    }

    private function matrixSourceCode(string $serial): ?string
    {
        $leaf = \App\Services\Deliverables\ProgramDeliverablesMatrix::findLeafBySerial($serial);
        $source = is_array($leaf['source'] ?? null) ? $leaf['source'] : [];

        return $source['deliverable_code'] ?? $source['code'] ?? null;
    }
}
