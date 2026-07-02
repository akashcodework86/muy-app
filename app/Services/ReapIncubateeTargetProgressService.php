<?php

namespace App\Services;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\ServiceCase;
use App\Support\ConvergenceReapSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use App\Support\ReapIncubateeTargets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReapIncubateeTargetProgressService
{
    public function __construct(
        private readonly LegacyApplicationServiceCaseSupport $legacyServiceCases,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function districtProgress(?int $districtId, ?FiscalYear $fiscalYear = null): ?array
    {
        if ($districtId === null || $districtId < 1) {
            return null;
        }

        $district = District::query()->find($districtId);
        if ($district === null) {
            return null;
        }

        $fiscalYear = $this->resolveFiscalYear($fiscalYear);
        $targets = ReapIncubateeTargets::targetsForDistrictSlug($district->slug);
        if (ReapIncubateeTargets::sumBuckets($targets) <= 0) {
            return null;
        }

        $approved = $this->approvedCountsByDistrict($fiscalYear)[$districtId] ?? ReapIncubateeTargets::emptyBucketCounts();
        $summary = ReapIncubateeTargets::buildProgressSummary($targets, $approved);

        return [
            'fiscal_year' => $this->fiscalYearMeta($fiscalYear),
            'district' => [
                'id' => (int) $district->id,
                'name' => (string) $district->name,
                'slug' => (string) $district->slug,
            ],
            ...$summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statewideProgress(?FiscalYear $fiscalYear = null): array
    {
        $fiscalYear = $this->resolveFiscalYear($fiscalYear);
        $approvedByDistrict = $this->approvedCountsByDistrict($fiscalYear);

        $rows = [];
        $grandTargets = ReapIncubateeTargets::emptyBucketCounts();
        $grandApproved = ReapIncubateeTargets::emptyBucketCounts();

        $districts = District::query()
            ->with('hub:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $serial = 0;
        foreach ($districts as $district) {
            $targets = ReapIncubateeTargets::targetsForDistrictSlug($district->slug);
            if (ReapIncubateeTargets::sumBuckets($targets) <= 0) {
                continue;
            }

            $serial++;
            $approved = $approvedByDistrict[(int) $district->id] ?? ReapIncubateeTargets::emptyBucketCounts();
            $summary = ReapIncubateeTargets::buildProgressSummary($targets, $approved);

            foreach (ReapIncubateeTargets::bucketKeys() as $bucket) {
                $grandTargets[$bucket] += (int) ($targets[$bucket] ?? 0);
                $grandApproved[$bucket] += (int) ($approved[$bucket] ?? 0);
            }

            $rows[] = [
                'serial' => $serial,
                'district' => [
                    'id' => (int) $district->id,
                    'name' => (string) $district->name,
                    'slug' => (string) $district->slug,
                    'hub_name' => (string) ($district->hub?->name ?? '—'),
                ],
                ...$summary,
            ];
        }

        return [
            'fiscal_year' => $this->fiscalYearMeta($fiscalYear),
            'rows' => $rows,
            'grand_totals' => ReapIncubateeTargets::buildProgressSummary($grandTargets, $grandApproved),
        ];
    }

    private function resolveFiscalYear(?FiscalYear $fiscalYear): FiscalYear
    {
        if ($fiscalYear !== null) {
            return $fiscalYear;
        }

        $configuredCode = ReapIncubateeTargets::configuredFiscalYearCode();

        return FiscalYear::query()->where('code', $configuredCode)->first()
            ?? FiscalYear::phase3Default()
            ?? FiscalYear::forUiDropdown()->first()
            ?? new FiscalYear([
                'code' => $configuredCode,
                'name' => 'FY '.$configuredCode,
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
            ]);
    }

    /**
     * @return array{id: int|null, code: string, name: string}
     */
    private function fiscalYearMeta(FiscalYear $fiscalYear): array
    {
        return [
            'id' => $fiscalYear->id ? (int) $fiscalYear->id : null,
            'code' => (string) ($fiscalYear->code ?? ReapIncubateeTargets::configuredFiscalYearCode()),
            'name' => (string) ($fiscalYear->name ?? ('FY '.ReapIncubateeTargets::configuredFiscalYearCode())),
        ];
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function approvedCountsByDistrict(FiscalYear $fiscalYear): array
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return [];
        }

        $periodFrom = Carbon::parse($fiscalYear->starts_on)->startOfDay();
        $periodTo = Carbon::parse($fiscalYear->ends_on)->endOfDay();
        $floor = Carbon::parse((string) config('program_deliverables.phase3_floor_date', '2026-04-01'))->startOfDay();
        if ($periodTo->gte($floor) && $periodFrom->lt($floor)) {
            $periodFrom = $floor->copy();
        }

        $dateExpr = $this->achievementDateExpression();
        $legacyDistrictMap = $this->legacyApplicationDistrictMap();

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);

        ConvergenceReapSupportDeliverablesSupport::applyAchievementScope($query, 'sc');

        $query->whereBetween(DB::raw($dateExpr), [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()]);

        $sectorExpr = $this->payloadExpression(ConvergenceReapSupport::REAP_SECTOR_KEY);
        $amountExpr = $this->payloadExpression(ConvergenceReapSupport::REAP_AMOUNT_KEY);

        $rows = $query->get([
            'sc.id',
            'cs.district_id',
            'sc.legacy_application_id',
            DB::raw($sectorExpr.' as reap_sector'),
            DB::raw($amountExpr.' as reap_amount'),
        ]);

        $counts = [];
        foreach ($rows as $row) {
            $districtId = (int) ($row->district_id ?? 0);
            if ($districtId < 1) {
                $legacyId = (int) ($row->legacy_application_id ?? 0);
                $districtId = (int) ($legacyDistrictMap[$legacyId] ?? 0);
            }
            if ($districtId < 1) {
                continue;
            }

            $bucket = ReapIncubateeTargets::bucketFromPayload(
                is_string($row->reap_sector ?? null) ? $row->reap_sector : null,
                is_string($row->reap_amount ?? null) ? $row->reap_amount : null,
            );
            if ($bucket === null) {
                continue;
            }

            if (! isset($counts[$districtId])) {
                $counts[$districtId] = ReapIncubateeTargets::emptyBucketCounts();
            }

            $counts[$districtId][$bucket]++;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function legacyApplicationDistrictMap(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $districts = District::query()->orderBy('id')->get(['id']);
        foreach ($districts as $district) {
            foreach ($this->legacyServiceCases->legacyApplicationIdsInLaravelDistrict((int) $district->id) as $legacyId) {
                $cache[(int) $legacyId] = (int) $district->id;
            }
        }

        return $cache;
    }

    private function achievementDateExpression(): string
    {
        $parts = [];
        foreach (['approved_at', 'completed_at', 'delivered_on', 'submitted_at', 'created_at'] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                $parts[] = 'sc.'.$column;
            }
        }

        if ($parts === []) {
            return 'sc.created_at';
        }

        return count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
    }

    private function payloadExpression(string $key): string
    {
        $driver = Schema::getConnection()->getDriverName();
        $jsonPath = '$."'.$key.'"';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "LOWER(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(sc.payload, '".$jsonPath."')) AS CHAR), ''))";
        }

        if ($driver === 'sqlite') {
            return "LOWER(COALESCE(json_extract(sc.payload, '$.".$key."'), ''))";
        }

        return "LOWER(COALESCE(sc.payload->>'".$key."', ''))";
    }
}
