<?php

namespace App\Services\Cfa;

use App\Models\District;
use App\Models\FiscalYear;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CfaFyOnboardingStatsService
{
    /**
     * Locked hub-batch onboardings in the active Phase 3 FY, split by applicant cohort (phase).
     *
     * @return array{
     *     fiscal_year_code: string|null,
     *     total: int,
     *     phase1: int,
     *     phase2: int,
     *     phase3: int
     * }
     */
    public static function breakdown(?int $districtId = null): array
    {
        $empty = [
            'fiscal_year_code' => null,
            'total' => 0,
            'phase1' => 0,
            'phase2' => 0,
            'phase3' => 0,
        ];

        $fy = FiscalYear::phase3Default();
        if ($fy === null) {
            return $empty;
        }

        $fyCode = (string) ($fy->code ?? $fy->name);

        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return array_merge($empty, ['fiscal_year_code' => $fyCode]);
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->whereNotNull('ob.onboarding_date')
            ->whereBetween('ob.onboarding_date', [
                $fy->starts_on->toDateString(),
                $fy->ends_on->toDateString(),
            ]);

        if ($districtId !== null && $districtId > 0) {
            $query->where('cs.district_id', $districtId);
        }

        $phase2Sql = "LOWER(TRIM(COALESCE(cs.source, ''))) IN ('legacy_phase2', 'rbiphase2')";
        $phase1Sql = "LOWER(TRIM(COALESCE(cs.source, ''))) IN ('legacy_phase1', 'rbiphase1')";

        $misTotal = (int) (clone $query)->count();
        $phase1Mis = (int) (clone $query)->whereRaw($phase1Sql)->count();
        $phase2 = (int) (clone $query)->whereRaw($phase2Sql)->count();
        $phase3 = max(0, $misTotal - $phase1Mis - $phase2);

        $legacyPhase1 = self::legacyPhase1OnboardedInFy($fy, $districtId);
        $phase1 = $phase1Mis + $legacyPhase1;

        return [
            'fiscal_year_code' => $fyCode,
            'total' => $misTotal + $legacyPhase1,
            'phase1' => $phase1,
            'phase2' => $phase2,
            'phase3' => $phase3,
        ];
    }

    private static function legacyPhase1OnboardedInFy(FiscalYear $fy, ?int $districtId): int
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return 0;
        }

        try {
            if (! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }

        $dateColumn = null;
        foreach (['onboarding_date', 'onboard_date'] as $candidate) {
            if (Schema::connection('legacy_phase1')->hasColumn('tblapplication', $candidate)) {
                $dateColumn = $candidate;
                break;
            }
        }

        if ($dateColumn === null) {
            return 0;
        }

        $query = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes'])
            ->whereNotNull($dateColumn)
            ->where($dateColumn, '!=', '')
            ->whereBetween(DB::raw('DATE(`'.$dateColumn.'`)'), [
                $fy->starts_on->toDateString(),
                $fy->ends_on->toDateString(),
            ]);

        if ($districtId !== null && $districtId > 0) {
            $district = District::query()->find($districtId);
            if ($district === null) {
                return 0;
            }
            $keys = LegacyPhase1DistrictResolver::legacyKeysForDistrict($district->name);
            if ($keys === []) {
                return 0;
            }
            $query->where(function ($q) use ($keys): void {
                foreach ($keys as $key) {
                    $q->orWhereRaw('LOWER(TRIM(FatherName)) = ?', [mb_strtolower($key)]);
                }
            });
        }

        return (int) $query->count();
    }
}
