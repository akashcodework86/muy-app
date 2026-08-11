<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Services\BatchMemberInterventionsSummaryService;
use App\Support\PotentialLakhpatiOnboardingSql;
use App\Support\SimpleXlsxWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarded-only SHG + CBO pack across Phase 1 / 2 / 3 for one district.
 * Includes CFA date, onboarded status, and services given.
 */
final class OnboardedShgCboDistrictPackService
{
    /** Synthetic service-lookup keys so Phase 2/1 ids never collide with real CFA ids. */
    private const SERVICE_KEY_PHASE2 = 1_000_000_000;

    private const SERVICE_KEY_PHASE1 = 2_000_000_000;

    public function __construct(
        private readonly BatchMemberInterventionsSummaryService $interventions,
    ) {}

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     shg: array{label: string, count: int, details: list<array<string, mixed>>, by_phase: array<string, int>},
     *     cbo: array{label: string, count: int, details: list<array<string, mixed>>, by_phase: array<string, int>},
     *     individual: array{label: string, count: int, details: list<array<string, mixed>>, by_phase: array<string, int>},
     *     phase1: array{label: string, count: int, details: list<array<string, mixed>>}
     * }
     */
    public function build(?int $districtId = null, ?string $districtSlug = null): array
    {
        $district = $this->resolveDistrict($districtId, $districtSlug);
        $districtId = $district ? (int) $district->id : null;
        $districtLabel = $district?->name ?? 'All districts';
        $districtNames = $this->legacyDistrictNames($district);

        $phase3Shg = $this->phase3LockedMembers($districtId, 'shg');
        $phase3Cbo = $this->phase3LockedMembers($districtId, 'cbo');
        $phase3Individual = $this->phase3LockedMembers($districtId, 'individual');
        $phase2Shg = $this->phase2OnboardedMembers($districtNames, 'shg');
        $phase2Cbo = $this->phase2OnboardedMembers($districtNames, 'cbo');
        $phase2Individual = $this->phase2OnboardedMembers($districtNames, 'individual');
        $phase1 = $this->phase1Onboarded($districtNames);
        $phase1ForIndividual = $this->phase1AsIndividualRows($phase1);

        $shgDetails = $this->tagCohort($this->mergeDeduped($phase3Shg, $phase2Shg), 'shg');
        $cboDetails = $this->tagCohort($this->mergeDeduped($phase3Cbo, $phase2Cbo), 'cbo');
        $individualDetails = $this->tagCohort(
            $this->mergeDeduped($phase3Individual, $phase2Individual, $phase1ForIndividual),
            'individual'
        );

        // Resolve cross-phase interventions once. Running the same legacy lookups
        // independently for each cohort makes the all-district explorer unnecessarily slow.
        $allDetails = $this->attachServicesToDetails(array_merge($shgDetails, $cboDetails, $individualDetails));
        $shgDetails = $this->takeCohort($allDetails, 'shg');
        $cboDetails = $this->takeCohort($allDetails, 'cbo');
        $individualDetails = $this->takeCohort($allDetails, 'individual');
        $phase1 = array_values(array_filter(
            $individualDetails,
            static fn (array $row): bool => ($row['phase'] ?? '') === 'Phase 1'
        ));

        return [
            'meta' => [
                'title' => 'Onboarded SHG / CBO / Individual (Phase 1+2+3) — '.$districtLabel,
                'district' => $districtLabel,
                'district_id' => $districtId,
                'district_slug' => $district?->slug ?? 'all',
                'as_of' => now()->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'rules' => [
                    'ONLY onboarded records',
                    'Phase 3: locked onboarding batches (MIS)',
                    'Phase 2: rbi_onboarded_applicants (legacy Phase 2 DB)',
                    'Phase 1: ukrbiin_rbi.tblapplication WHERE onboard = yes (null/blank = not onboarded, excluded)',
                    'SHG = Individual/member Yes OR category SHG (Phase 2 & 3)',
                    'CBO = category CBO (Phase 2 & 3)',
                    'Individual = Phase 2/3 category Individual + member No, PLUS all Phase 1 onboarded (no SHG/CBO columns in Phase 1 DB)',
                    'CFA Date = application / submission date',
                    'Onboarded Status = Onboarded (+ legacy status when available)',
                    'Services = Phase 1/2/3 interventions + market linkage',
                    'District filter: '.$districtLabel,
                ],
            ],
            'shg' => [
                'label' => 'SHG onboarded (member Yes or category SHG) — Phase 2 + Phase 3',
                'count' => count($shgDetails),
                'details' => $shgDetails,
                'by_phase' => $this->countByPhase($shgDetails),
            ],
            'cbo' => [
                'label' => 'CBO onboarded (category CBO) — Phase 2 + Phase 3',
                'count' => count($cboDetails),
                'details' => $cboDetails,
                'by_phase' => $this->countByPhase($cboDetails),
            ],
            'individual' => [
                'label' => 'Individual onboarded — Phase 1 (tblapplication onboard=yes) + Phase 2/3 (not SHG/CBO)',
                'count' => count($individualDetails),
                'details' => $individualDetails,
                'by_phase' => $this->countByPhase($individualDetails),
            ],
            'phase1' => [
                'label' => 'Phase 1 onboarded from ukrbiin_rbi.tblapplication (onboard=yes)',
                'count' => count($phase1),
                'details' => $phase1,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    public function writeToPath(array $pack, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $meta = $pack['meta'] ?? [];
        $shg = $pack['shg'] ?? [];
        $cbo = $pack['cbo'] ?? [];
        $individual = $pack['individual'] ?? [];
        $phase1 = $pack['phase1'] ?? [];

        $writer = new SimpleXlsxWriter;

        $readme = [
            [(string) ($meta['title'] ?? 'Onboarded SHG / CBO / Individual')],
            [],
            ['District', (string) ($meta['district'] ?? '')],
            ['Generated at', (string) ($meta['as_of'] ?? '')],
            [],
            ['Rules'],
        ];
        foreach (($meta['rules'] ?? []) as $rule) {
            $readme[] = ['• '.(string) $rule];
        }
        $writer->addSheet('README', $readme);

        foreach ([
            ['SHG', $shg],
            ['CBO', $cbo],
            ['Individual', $individual],
        ] as [$title, $cohort]) {
            $byPhase = $cohort['by_phase'] ?? [];
            $writer->addSheet($title.' Counts', [
                ['Section', 'Metric', 'Count'],
                ['Cohort', (string) ($cohort['label'] ?? $title), ''],
                ['Cohort', 'Total onboarded '.$title.' (deduped)', (int) ($cohort['count'] ?? 0)],
                ['By phase', 'Phase 3', (int) ($byPhase['Phase 3'] ?? 0)],
                ['By phase', 'Phase 2', (int) ($byPhase['Phase 2'] ?? 0)],
                ['By phase', 'Phase 1', (int) ($byPhase['Phase 1'] ?? 0)],
                ['Cohort', 'Detail rows (must match)', count($cohort['details'] ?? [])],
            ]);
            $writer->addSheet($title.' Detail', $this->detailRows($cohort['details'] ?? []));
        }

        $writer->addSheet('Phase1 Counts', [
            ['Section', 'Metric', 'Count'],
            ['Cohort', (string) ($phase1['label'] ?? 'Phase 1'), ''],
            ['Source', 'Database ukrbiin_rbi · Table tblapplication', ''],
            ['Filter', "onboard = 'yes' (null/blank excluded — not onboarded)", ''],
            ['Cohort', 'Total Phase 1 onboarded', (int) ($phase1['count'] ?? 0)],
            ['Note', 'Also included in Individual Counts/Detail (Phase 1 has no SHG/CBO columns)', ''],
        ]);
        $writer->addSheet('Phase1 Detail', $this->phase1DetailRows($phase1['details'] ?? []));

        $writer->save($absolutePath);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<list<string|int>>
     */
    private function detailRows(array $details): array
    {
        $headers = [
            'S.N.', 'Phase', 'CFA / App ID', 'Application No', 'Applicant', 'Category', 'Member of SHG/CBO',
            'SHG/CBO Name', 'Phone', 'Sector', 'District', 'Hub / Block', 'Batch',
            'CFA Date', 'Onboarding Date', 'Onboarded Status',
            'Services count', 'Services given',
        ];
        $keys = [
            'sn', 'phase', 'cfa_id', 'application_no', 'applicant', 'category', 'is_member',
            'shg_cbo_name', 'phone', 'sector', 'district', 'hub', 'batch',
            'cfa_date', 'onboarding_date', 'onboarded_status',
            'services_count', 'services',
        ];

        return $this->rowsFromKeys($headers, $keys, $details);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<list<string|int>>
     */
    private function phase1DetailRows(array $details): array
    {
        $headers = [
            'S.N.', 'Phase', 'App ID', 'Application No', 'Applicant', 'Phone', 'Gender',
            'District', 'City / Block',
            'CFA Date', 'Onboarding Date', 'Onboarded Status',
            'Services count', 'Services given',
        ];
        $keys = [
            'sn', 'phase', 'cfa_id', 'application_no', 'applicant', 'phone', 'gender',
            'district', 'hub',
            'cfa_date', 'onboarding_date', 'onboarded_status',
            'services_count', 'services',
        ];

        return $this->rowsFromKeys($headers, $keys, $details);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $keys
     * @param  list<array<string, mixed>>  $details
     * @return list<list<string|int>>
     */
    private function rowsFromKeys(array $headers, array $keys, array $details): array
    {
        $out = [$headers];
        foreach ($details as $row) {
            $line = [];
            foreach ($keys as $key) {
                $val = $row[$key] ?? '';
                $line[] = is_numeric($val) && ! in_array($key, ['application_no', 'phone'], true)
                    ? (0 + $val)
                    : (string) $val;
            }
            $out[] = $line;
        }

        return $out;
    }

    private function resolveDistrict(?int $districtId, ?string $districtSlug): ?District
    {
        if ($districtId && $districtId > 0) {
            return District::query()->find($districtId);
        }

        $slug = strtolower(trim((string) $districtSlug));
        if ($slug === '') {
            return null;
        }

        return District::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [$slug])
            ->first();
    }

    /**
     * @return list<string>
     */
    private function legacyDistrictNames(?District $district): array
    {
        if (! $district) {
            return [];
        }

        $canonical = trim((string) $district->name);
        $names = [$canonical];

        $p2Aliases = (array) config('legacy_phase2.staff_import.district_aliases', []);
        foreach ((array) ($p2Aliases[$canonical] ?? []) as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $names[] = $alias;
            }
        }

        $p1Aliases = (array) config('legacy_phase1.district_aliases', []);
        foreach ((array) ($p1Aliases[$canonical] ?? []) as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $names[] = $alias;
            }
        }

        return array_values(array_unique(array_map(
            static fn (string $n): string => strtolower(trim($n)),
            $names
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase3LockedMembers(?int $districtId, string $cohort): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return [];
        }

        $categoryJson = PotentialLakhpatiOnboardingSql::payloadJson('$.category');
        $appCategoryJson = PotentialLakhpatiOnboardingSql::payloadJson('$.app_category');
        $memberJson = PotentialLakhpatiOnboardingSql::payloadJson('$.is_member');
        $shgNameJson = PotentialLakhpatiOnboardingSql::payloadJson('$.shg_name');
        $shgCboNameJson = PotentialLakhpatiOnboardingSql::payloadJson('$.shg_cbo_name');
        $phoneJson = PotentialLakhpatiOnboardingSql::payloadJson('$.phone');
        $sectorJson = PotentialLakhpatiOnboardingSql::payloadJson('$.sector');
        $businessCategoryJson = PotentialLakhpatiOnboardingSql::payloadJson('$.business_category');
        $genderJson = PotentialLakhpatiOnboardingSql::payloadJson('$.gender');
        $educationJson = PotentialLakhpatiOnboardingSql::payloadJson('$.education');
        $stageJson = PotentialLakhpatiOnboardingSql::payloadJson('$.form_stage');
        $legacyIdJson = PotentialLakhpatiOnboardingSql::payloadJson('$.legacy_application_id');

        $memberYes = $this->payloadMemberYesSql();
        $catExpr = "LOWER(TRIM(COALESCE({$categoryJson}, {$appCategoryJson}, '')))";
        if ($cohort === 'cbo') {
            $qualify = "({$catExpr} = 'cbo')";
        } elseif ($cohort === 'individual') {
            // Every locked batch member belongs to Phase 3 reporting. Blank or
            // historical categories fall back to Individual instead of being
            // excluded from the official onboarding total.
            $qualify = "NOT (
                {$catExpr} IN ('shg', 'cbo')
                OR ({$catExpr} = 'individual' AND {$memberYes})
            )";
        } else {
            $qualify = "(
                {$catExpr} = 'shg'
                OR (
                    {$catExpr} = 'individual'
                    AND {$memberYes}
                )
            )";
        }

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->whereRaw($qualify);

        if ($districtId && $districtId > 0) {
            $query->where('cs.district_id', $districtId);
        }

        $rows = $query
            ->orderBy('cs.applicant_name')
            ->get([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone as cfa_phone',
                'cs.created_at',
                'd.name as district_name',
                'h.name as hub_name',
                'ob.name as batch_name',
                'ob.onboarding_date',
                'ob.status as batch_status',
                DB::raw("{$categoryJson} as category"),
                DB::raw("{$memberJson} as is_member"),
                DB::raw("{$shgNameJson} as shg_name"),
                DB::raw("{$shgCboNameJson} as shg_cbo_name"),
                DB::raw("{$phoneJson} as payload_phone"),
                DB::raw("{$sectorJson} as sector"),
                DB::raw("{$businessCategoryJson} as business_category"),
                DB::raw("{$genderJson} as gender"),
                DB::raw("{$educationJson} as education"),
                DB::raw("{$stageJson} as business_stage"),
                DB::raw("{$legacyIdJson} as legacy_application_id"),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'phase' => 'Phase 3',
                'cfa_id' => (int) $row->id,
                'application_no' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'category' => (string) ($row->category ?: '—'),
                'is_member' => (string) ($row->is_member ?: '—'),
                'shg_cbo_name' => (string) (($row->shg_cbo_name ?: $row->shg_name) ?: '—'),
                'phone' => (string) (($row->cfa_phone ?: $row->payload_phone) ?: ''),
                'sector' => (string) (($row->business_category ?: $row->sector) ?: '—'),
                'gender' => (string) ($row->gender ?: '—'),
                'education' => (string) ($row->education ?: '—'),
                'business_stage' => (string) ($row->business_stage ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'batch' => (string) ($row->batch_name ?: '—'),
                'cfa_date' => $this->formatDate($row->created_at ?? null),
                'onboarding_date' => $this->formatDate($row->onboarding_date ?? null),
                'onboarded_status' => 'Onboarded (locked batch)',
                'legacy_application_id' => (int) ($row->legacy_application_id ?? 0),
                'legacy_phase1_id' => 0,
                'service_lookup_id' => (int) $row->id,
                'dedupe_key' => $this->dedupeKey(
                    (string) ($row->application_no ?? ''),
                    (int) ($row->legacy_application_id ?? 0),
                    'p3:'.(int) $row->id,
                ),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $districtNamesLower
     * @return list<array<string, mixed>>
     */
    private function phase2OnboardedMembers(array $districtNamesLower, string $cohort): array
    {
        if (! $this->legacyPhase2Ready()) {
            return [];
        }

        $query = DB::connection('legacy')
            ->table('rbi_onboarded_applicants as oa')
            ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->leftJoin('rbi_onboarding_batches as lob', 'lob.id', '=', 'oa.onboarding_batch_id')
            ->whereNotNull('oa.status')
            ->where('oa.status', '<>', '');

        if ($districtNamesLower !== []) {
            $query->where(function ($q) use ($districtNamesLower): void {
                foreach ($districtNamesLower as $name) {
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(d.district, \'\'))) = ?', [$name]);
                }
            });
        }

        if ($cohort === 'cbo') {
            $query->whereRaw("LOWER(TRIM(COALESCE(a.category, ''))) = 'cbo'");
        } elseif ($cohort === 'individual') {
            $query->whereRaw("LOWER(TRIM(COALESCE(a.category, ''))) = 'individual'")
                ->whereRaw("LOWER(TRIM(COALESCE(CAST(d.is_shg_member AS CHAR), ''))) NOT IN ('yes', 'y', '1', 'true')");
        } else {
            $query->where(function ($q): void {
                $q->whereRaw("LOWER(TRIM(COALESCE(CAST(d.is_shg_member AS CHAR), ''))) IN ('yes', 'y', '1', 'true')")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(a.category, ''))) = 'shg'");
            });
        }

        $rows = $query
            ->orderBy('d.applicant_name')
            ->get([
                'a.id',
                'a.application_no',
                'a.category',
                'a.business_category',
                'a.form_stage',
                'a.submission_date',
                'a.created_at as app_created_at',
                'd.applicant_name',
                'd.phone',
                'd.gender',
                'd.education',
                'd.is_shg_member',
                'd.shg_name',
                'd.district',
                'd.block',
                'lob.batch_name',
                'lob.onboarding_date',
                'oa.onboarded_at',
                'oa.status as onboard_status',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $appId = (int) $row->id;
            $statusRaw = trim((string) ($row->onboard_status ?? ''));
            $onboardedAt = $row->onboarded_at ?: ($row->onboarding_date ?? null);
            $cfaDate = $row->submission_date ?: ($row->app_created_at ?? null);

            $out[] = [
                'phase' => 'Phase 2',
                'cfa_id' => $appId,
                'application_no' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'category' => (string) ($row->category ?: '—'),
                'is_member' => (string) ($row->is_shg_member ?: '—'),
                'shg_cbo_name' => (string) ($row->shg_name ?: '—'),
                'phone' => (string) ($row->phone ?: ''),
                'sector' => (string) ($row->business_category ?: '—'),
                'gender' => (string) ($row->gender ?: '—'),
                'education' => (string) ($row->education ?: '—'),
                'business_stage' => (string) ($row->form_stage ?: '—'),
                'district' => (string) ($row->district ?: '—'),
                'hub' => (string) ($row->block ?: '—'),
                'batch' => (string) ($row->batch_name ?: '—'),
                'cfa_date' => $this->formatDate($cfaDate),
                'onboarding_date' => $this->formatDate($onboardedAt),
                'onboarded_status' => $statusRaw !== ''
                    ? 'Onboarded ('.$statusRaw.')'
                    : 'Onboarded',
                'legacy_application_id' => $appId,
                'legacy_phase1_id' => 0,
                'service_lookup_id' => self::SERVICE_KEY_PHASE2 + $appId,
                'dedupe_key' => $this->dedupeKey(
                    (string) ($row->application_no ?? ''),
                    $appId,
                    'p2:'.$appId,
                ),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $districtNamesLower
     * @return list<array<string, mixed>>
     */
    private function phase1Onboarded(array $districtNamesLower): array
    {
        if (! $this->legacyPhase1Ready()) {
            return [];
        }

        $query = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereRaw("LOWER(TRIM(COALESCE(onboard, ''))) = 'yes'");

        if ($districtNamesLower !== []) {
            $query->where(function ($q) use ($districtNamesLower): void {
                foreach ($districtNamesLower as $name) {
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(FatherName, \'\'))) = ?', [$name])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(FatherName, \'\'))) LIKE ?', ['%'.$name.'%']);
                }
            });
        }

        $select = [
            'ID',
            'ApplicationNumber',
            'FullName',
            'MobileNumber',
            'gender',
            'FatherName',
            'City',
            'onboarding_date',
            'onboard_date',
            'onboard',
        ];
        foreach (['ApplicationDate', 'education', 'idea', 'current_status', 'Occupationtype', 'self_declearation'] as $optionalColumn) {
            if (Schema::connection('legacy_phase1')->hasColumn('tblapplication', $optionalColumn)) {
                $select[] = $optionalColumn;
            }
        }

        $rows = $query->orderBy('FullName')->get($select);

        $out = [];
        $sn = 0;
        foreach ($rows as $row) {
            $sn++;
            $appId = (int) $row->ID;
            $dateRaw = $row->onboarding_date ?: ($row->onboard_date ?? null);
            $cfaDate = $row->ApplicationDate ?? null;

            $out[] = [
                'sn' => $sn,
                'phase' => 'Phase 1',
                'cfa_id' => $appId,
                'application_no' => (string) ($row->ApplicationNumber ?: '—'),
                'applicant' => (string) ($row->FullName ?: '—'),
                'phone' => (string) ($row->MobileNumber ?: ''),
                'gender' => (string) ($row->gender ?: '—'),
                'education' => (string) (($row->education ?? null) ?: '—'),
                'sector' => (string) (($row->idea ?? null) ?: '—'),
                'business_stage' => (string) (($row->current_status ?? null) ?: '—'),
                'category' => (string) (($row->Occupationtype ?? null) ?: 'Individual'),
                'self_declaration' => (string) (($row->self_declearation ?? null) ?: ''),
                'district' => (string) ($row->FatherName ?: '—'),
                'hub' => (string) ($row->City ?: '—'),
                'cfa_date' => $this->formatDate($cfaDate),
                'onboarding_date' => $this->formatDate($dateRaw),
                'onboarded_status' => 'Onboarded (tblapplication.onboard=yes)',
                'legacy_application_id' => 0,
                'legacy_phase1_id' => $appId,
                'service_lookup_id' => self::SERVICE_KEY_PHASE1 + $appId,
                'dedupe_key' => $this->dedupeKey(
                    (string) ($row->ApplicationNumber ?? ''),
                    $appId,
                    'p1:'.$appId,
                ),
            ];
        }

        return $out;
    }

    /**
     * Map Phase 1 onboarded rows into Individual-sheet shape.
     * Phase 1 tblapplication has no SHG/CBO columns — treat as Individual.
     *
     * @param  list<array<string, mixed>>  $phase1Rows
     * @return list<array<string, mixed>>
     */
    private function phase1AsIndividualRows(array $phase1Rows): array
    {
        $out = [];
        foreach ($phase1Rows as $row) {
            $out[] = [
                'phase' => 'Phase 1',
                'cfa_id' => (int) ($row['cfa_id'] ?? 0),
                'application_no' => (string) ($row['application_no'] ?? '—'),
                'applicant' => (string) ($row['applicant'] ?? '—'),
                'category' => 'Individual',
                'is_member' => '—',
                'shg_cbo_name' => '—',
                'phone' => (string) ($row['phone'] ?? ''),
                'sector' => (string) ($row['sector'] ?? '—'),
                'gender' => (string) ($row['gender'] ?? '—'),
                'education' => (string) ($row['education'] ?? '—'),
                'business_stage' => (string) ($row['business_stage'] ?? '—'),
                'self_declaration' => (string) ($row['self_declaration'] ?? ''),
                'district' => (string) ($row['district'] ?? '—'),
                'hub' => (string) ($row['hub'] ?? '—'),
                'batch' => '—',
                'cfa_date' => (string) ($row['cfa_date'] ?? '—'),
                'onboarding_date' => (string) ($row['onboarding_date'] ?? '—'),
                'onboarded_status' => (string) ($row['onboarded_status'] ?? 'Onboarded (tblapplication.onboard=yes)'),
                'legacy_application_id' => 0,
                'legacy_phase1_id' => (int) ($row['legacy_phase1_id'] ?? $row['cfa_id'] ?? 0),
                'service_lookup_id' => (int) ($row['service_lookup_id'] ?? 0),
                'dedupe_key' => (string) ($row['dedupe_key'] ?? ('p1:'.(int) ($row['cfa_id'] ?? 0))),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private function attachServicesToDetails(array $details): array
    {
        if ($details === []) {
            return [];
        }

        $members = [];
        foreach ($details as $i => $row) {
            $lookupId = (int) ($row['service_lookup_id'] ?? $row['cfa_id'] ?? 0);
            if ($lookupId <= 0) {
                continue;
            }
            $members[] = [
                'id' => $lookupId,
                'application_no' => (string) ($row['application_no'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'source' => strtolower(str_replace(' ', '', (string) ($row['phase'] ?? ''))),
                'legacy_application_id' => (int) ($row['legacy_application_id'] ?? 0),
                'legacy_phase1_id' => (int) ($row['legacy_phase1_id'] ?? 0),
                '_idx' => $i,
            ];
        }

        $withServices = $this->interventions->attachServices($members);
        $byIdx = [];
        foreach ($withServices as $member) {
            $idx = (int) ($member['_idx'] ?? -1);
            if ($idx < 0) {
                continue;
            }
            $byIdx[$idx] = $member['services'] ?? [];
        }

        foreach ($details as $i => &$row) {
            $services = $byIdx[$i] ?? [];
            $labels = [];
            foreach ($services as $svc) {
                $label = trim((string) ($svc['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $status = trim((string) ($svc['status'] ?? ''));
                $detail = trim((string) ($svc['detail'] ?? ''));
                $bit = $label;
                if ($detail !== '') {
                    $bit .= ' ('.$detail.')';
                }
                if ($status !== '') {
                    $bit .= ' ['.$status.']';
                }
                $labels[] = $bit;
            }
            $row['services_count'] = count($labels);
            $row['services'] = $labels !== [] ? implode(' | ', $labels) : '—';
            $row['service_items'] = array_values(array_map(static fn (array $svc): array => [
                'phase' => (string) ($svc['phase'] ?? ''),
                'label' => trim((string) ($svc['label'] ?? '')),
                'detail' => trim((string) ($svc['detail'] ?? '')),
                'status' => trim((string) ($svc['status'] ?? '')),
                'date' => trim((string) ($svc['date'] ?? '')),
            ], array_filter(
                $services,
                static fn (array $svc): bool => trim((string) ($svc['label'] ?? '')) !== ''
            )));
            unset($row['service_lookup_id'], $row['legacy_application_id'], $row['legacy_phase1_id'], $row['dedupe_key']);
        }
        unset($row);

        return $details;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function tagCohort(array $rows, string $cohort): array
    {
        return array_map(static function (array $row) use ($cohort): array {
            $row['_cohort'] = $cohort;

            return $row;
        }, $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function takeCohort(array $rows, string $cohort): array
    {
        return array_values(array_map(static function (array $row): array {
            unset($row['_cohort']);

            return $row;
        }, array_filter(
            $rows,
            static fn (array $row): bool => ($row['_cohort'] ?? '') === $cohort
        )));
    }

    /**
     * Prefer earlier groups when the same application appears (Phase 3 → Phase 2 → Phase 1).
     *
     * @param  list<array<string, mixed>>  ...$groups
     * @return list<array<string, mixed>>
     */
    private function mergeDeduped(array ...$groups): array
    {
        $seen = [];
        $merged = [];

        foreach ($groups as $group) {
            foreach ($group as $row) {
                $key = (string) ($row['dedupe_key'] ?? '');
                if ($key !== '' && isset($seen[$key])) {
                    continue;
                }
                if ($key !== '') {
                    $seen[$key] = true;
                }
                $merged[] = $row;
            }
        }

        usort($merged, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['applicant'] ?? ''), (string) ($b['applicant'] ?? ''));
        });

        $sn = 0;
        foreach ($merged as &$row) {
            $sn++;
            $row['sn'] = $sn;
        }
        unset($row);

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return array<string, int>
     */
    private function countByPhase(array $details): array
    {
        $counts = ['Phase 1' => 0, 'Phase 2' => 0, 'Phase 3' => 0];
        foreach ($details as $row) {
            $phase = (string) ($row['phase'] ?? '');
            if (isset($counts[$phase])) {
                $counts[$phase]++;
            }
        }

        return $counts;
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function dedupeKey(string $applicationNo, int $legacyId, string $fallback): string
    {
        $app = strtolower(trim($applicationNo));
        if ($app !== '' && $app !== '—') {
            return 'app:'.$app;
        }
        if ($legacyId > 0) {
            return 'legacy:'.$legacyId;
        }

        return $fallback;
    }

    private function payloadMemberYesSql(string $payloadColumn = 'cs.payload'): string
    {
        $paths = ['$.is_member', '$.is_shg_member', '$.member_of_shg', '$.member_of_shg_cbo'];
        $parts = [];
        foreach ($paths as $path) {
            $json = PotentialLakhpatiOnboardingSql::payloadJson($path, $payloadColumn);
            $parts[] = "LOWER(TRIM(COALESCE(CAST({$json} AS CHAR), ''))) IN ('yes', 'y', '1', 'true')";
        }

        return '('.implode(' OR ', $parts).')';
    }

    private function legacyPhase2Ready(): bool
    {
        try {
            return Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
                && Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyPhase1Ready(): bool
    {
        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }
}
