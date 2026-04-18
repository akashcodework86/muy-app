<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\District;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full Phase-2 legacy read for a mirrored {@see CfaSubmission} (source legacy_phase2).
 * Mirrors joins used in {@see StaffPortalController::phase2BaseQueryForDistrict}.
 */
class LegacyPhase2ApplicationDetailService
{
    /**
     * @return array{
     *   viewRow: array<string, string>,
     *   legacy_application_id: int,
     *   rbi_applications: array<string, mixed>,
     *   rbi_applicant_details: array<string, mixed>,
     *   district_mismatch_warning: ?string
     * }|null
     */
    public function tryBuild(CfaSubmission $submission): ?array
    {
        if ($submission->source !== 'legacy_phase2') {
            return null;
        }

        $payload = is_array($submission->payload) ? $submission->payload : [];
        $legacyAppId = (int) ($payload['legacy_application_id'] ?? 0);
        if ($legacyAppId <= 0) {
            return null;
        }

        if (! $this->legacyAvailable()) {
            return null;
        }

        try {
            $row = $this->fetchJoinedRow($legacyAppId);
        } catch (\Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }

        $district = District::query()->find($submission->district_id);
        $districtName = trim((string) ($district?->name ?? ''));
        $legacyDistrict = trim((string) ($row->district ?? ''));

        $districtMismatchWarning = null;
        if ($districtName !== '' && $legacyDistrict !== '' && ! $this->legacyDistrictMatchesLaravel($districtName, $legacyDistrict)) {
            $districtMismatchWarning = 'Legacy row district ('.$legacyDistrict.') does not match this CFA’s district ('.$districtName.'). Data below is still shown from legacy; verify the correct application.';
        }

        $serviceRows = $this->servicesByApplicationIds([$legacyAppId])[$legacyAppId] ?? [];
        $viewRow = $this->buildPhase2ViewRow($row, $serviceRows);

        try {
            $appFull = DB::connection('legacy')->table('rbi_applications')->where('id', $legacyAppId)->first();
            $detailFull = DB::connection('legacy')
                ->table('rbi_applicant_details')
                ->where('application_id', $legacyAppId)
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable) {
            $appFull = null;
            $detailFull = null;
        }

        return [
            'viewRow' => $viewRow,
            'legacy_application_id' => $legacyAppId,
            'rbi_applications' => $appFull ? (array) $appFull : [],
            'rbi_applicant_details' => $detailFull ? (array) $detailFull : [],
            'district_mismatch_warning' => $districtMismatchWarning,
        ];
    }

    private function normDistrict(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return mb_strtolower($s);
    }

    /**
     * Same spirit as {@see HubBatchService::legacyDistrictNamesFor} — allow config aliases.
     */
    private function legacyDistrictMatchesLaravel(string $laravelDistrictName, string $legacyDistrictFromRow): bool
    {
        $legacyNorm = $this->normDistrict($legacyDistrictFromRow);
        $canonicalNorm = $this->normDistrict($laravelDistrictName);
        if ($legacyNorm === $canonicalNorm) {
            return true;
        }

        $aliasesMap = (array) config('legacy_phase2.staff_import.district_aliases', []);
        $aliases = array_map('trim', (array) ($aliasesMap[$laravelDistrictName] ?? []));
        foreach (array_merge([$laravelDistrictName], $aliases) as $a) {
            if ($this->normDistrict((string) $a) === $legacyNorm) {
                return true;
            }
        }

        foreach ($aliasesMap as $canonical => $aliasList) {
            if ($this->normDistrict((string) $canonical) !== $canonicalNorm) {
                continue;
            }
            foreach (array_merge([(string) $canonical], (array) $aliasList) as $a) {
                if ($this->normDistrict((string) $a) === $legacyNorm) {
                    return true;
                }
            }
        }

        return false;
    }

    private function legacyAvailable(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }
        try {
            return Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details')
                && Schema::connection('legacy')->hasTable('rbi_services_assigned');
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetchJoinedRow(int $applicationId): ?object
    {
        $hasEnterprise = false;
        try {
            $hasEnterprise = Schema::connection('legacy')->hasTable('rbi_enterprise_details');
        } catch (\Throwable) {
            $hasEnterprise = false;
        }

        $base = DB::connection('legacy')
            ->table('rbi_applicant_details as d')
            ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarded_applicants as oa', 'oa.application_id', '=', 'd.application_id')
            ->leftJoin('rbi_onboarding_batches as ob', 'ob.id', '=', 'oa.onboarding_batch_id')
            ->where('d.application_id', $applicationId);

        $select = [
            'd.application_id',
            'd.applicant_name',
            'd.phone',
            'd.district',
            'd.block',
            'd.village',
            'd.gender',
            'd.is_shg_member',
            'd.caste',
            'd.loan_taken',
            'd.bank_loan',
            'a.application_no',
            'a.product',
            'a.category as app_category',
            'a.form_stage',
            'a.submission_date',
            'a.created_at',
            'a.business_category',
            'ob.batch_name as cohort_name',
            'oa.status as onboard_status_db',
        ];

        if ($hasEnterprise) {
            $base->leftJoin(DB::raw('(
                SELECT e1.application_id, e1.turnover_last_year
                FROM rbi_enterprise_details e1
                INNER JOIN (
                    SELECT application_id, MAX(id) AS max_id
                    FROM rbi_enterprise_details
                    GROUP BY application_id
                ) t ON t.application_id = e1.application_id AND t.max_id = e1.id
            ) as ed'), 'ed.application_id', '=', 'd.application_id');
            $select[] = 'ed.turnover_last_year as turnover_last_year';
        } else {
            $select[] = DB::raw('NULL as turnover_last_year');
        }

        return $base->select($select)->first();
    }

    /**
     * @param  list<int>  $applicationIds
     * @return array<int, list<object>>
     */
    private function servicesByApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->whereIn('application_id', $applicationIds)
            ->orderBy('service_name')
            ->get(['application_id', 'service_name', 'category', 'service_number'])
            ->groupBy('application_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

    /**
     * @param  list<object>  $serviceRows
     * @return array<string, string>
     */
    private function buildPhase2ViewRow(object $row, array $serviceRows): array
    {
        $na = 'NA';
        $services = $this->summarizeServices($serviceRows);

        return [
            'application_no' => (string) ($row->application_no ?: $na),
            'applicant_name' => (string) ($row->applicant_name ?: $na),
            'phone' => (string) ($row->phone ?: $na),
            'gender' => (string) (($row->gender ?? '') !== '' ? $row->gender : $na),
            'caste' => (string) (($row->caste ?? '') !== '' ? $row->caste : $na),
            'is_shg_member' => (string) (($row->is_shg_member ?? '') !== '' ? $row->is_shg_member : $na),
            'district' => (string) ($row->district ?: $na),
            'block' => (string) ($row->block ?: $na),
            'village' => (string) ($row->village ?: $na),
            'product' => (string) ($row->product ?: $na),
            'app_category' => (string) ($row->app_category ?: $na),
            'form_stage' => (string) ($row->form_stage ?: $na),
            'submission_date' => $row->submission_date ? (string) $row->submission_date : $na,
            'business_category' => (string) ($row->business_category ?: $na),
            'turnover_last_year' => (string) (($row->turnover_last_year ?? '') !== '' ? $row->turnover_last_year : $na),
            'loan_taken' => (string) (($row->loan_taken ?? '') !== '' ? $row->loan_taken : $na),
            'bank_loan' => (string) (($row->bank_loan ?? '') !== '' ? $row->bank_loan : $na),
            'cohort_name' => (string) ($row->cohort_name ?: $na),
            'onboarding_status' => ! empty($row->onboard_status_db) ? 'yes' : 'no',
            'marketing_service' => $services['marketing_service'],
            'marketing_details' => $services['marketing_details'],
            'finance_service' => $services['finance_service'],
            'finance_details' => $services['finance_details'],
            'training_service' => $services['training_service'],
            'training_details' => $services['training_details'],
            'other_services_details' => $services['other_services_details'],
            'all_services' => $services['all_services'],
        ];
    }

    /**
     * @param  list<object>  $serviceRows
     * @return array<string, string>
     */
    private function summarizeServices(array $serviceRows): array
    {
        $na = 'NA';
        $allNames = [];
        $svc = [
            'marketing' => ['flag' => 'No', 'details' => []],
            'finance' => ['flag' => 'No', 'details' => []],
            'training' => ['flag' => 'No', 'details' => []],
            'other' => [],
        ];

        foreach ($serviceRows as $service) {
            $serviceName = trim((string) ($service->service_name ?? ''));
            $category = mb_strtolower(trim((string) ($service->category ?? '')));
            $nameLower = mb_strtolower($serviceName);

            if ($serviceName !== '') {
                $allNames[] = $serviceName;
            }

            $detailParts = [];
            if ($serviceName !== '') {
                $detailParts[] = $serviceName;
            }
            if (! empty($service->service_number)) {
                $detailParts[] = 'Ref: '.$service->service_number;
            }
            $detail = implode(' | ', $detailParts);

            $isMarketing = str_contains($nameLower, 'market') || str_contains($nameLower, 'brand');
            $isFinance = str_contains($nameLower, 'finance') || str_contains($nameLower, 'loan') || str_contains($category, 'finance');
            $isTraining = str_contains($nameLower, 'training') || str_contains($nameLower, 'workshop') || str_contains($nameLower, 'session') || str_contains($category, 'training');

            if ($isMarketing) {
                $svc['marketing']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['marketing']['details'][] = $detail;
                }
            } elseif ($isFinance) {
                $svc['finance']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['finance']['details'][] = $detail;
                }
            } elseif ($isTraining) {
                $svc['training']['flag'] = 'Yes';
                if ($detail !== '') {
                    $svc['training']['details'][] = $detail;
                }
            } elseif ($detail !== '') {
                $svc['other'][] = $detail;
            }
        }

        $allNames = array_values(array_unique($allNames));
        sort($allNames);

        return [
            'marketing_service' => $svc['marketing']['flag'],
            'marketing_details' => $svc['marketing']['details'] !== [] ? implode('; ', $svc['marketing']['details']) : $na,
            'finance_service' => $svc['finance']['flag'],
            'finance_details' => $svc['finance']['details'] !== [] ? implode('; ', $svc['finance']['details']) : $na,
            'training_service' => $svc['training']['flag'],
            'training_details' => $svc['training']['details'] !== [] ? implode('; ', $svc['training']['details']) : $na,
            'other_services_details' => $svc['other'] !== [] ? implode('; ', $svc['other']) : $na,
            'all_services' => $allNames !== [] ? implode(', ', $allNames) : $na,
        ];
    }
}
