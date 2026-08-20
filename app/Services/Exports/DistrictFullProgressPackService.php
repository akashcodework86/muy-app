<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\ServiceCase;
use App\Services\Deliverables\ProgramDeliverablesFilter;
use App\Services\Deliverables\ProgramDeliverablesScope;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\LegacyData\LegacyServiceNameNormalizer;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase1\LegacyPhase1ListQuery;
use App\Services\LegacyPhase2\LegacyPhase2DistrictResolver;
use App\Services\LegacyPhase2\LegacyPhase2ListQuery;
use App\Services\ProgramDeliverablesReportService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simple all-phase progress Excel: CFA, onboarded, and mapped deliverable counts.
 * Phase 1 = 2021-25, Phase 2 = 2025-26, Phase 3 = 2026-27.
 */
final class DistrictFullProgressPackService
{
    public const P1_LABEL = 'Phase 1 (2021-25)';

    public const P2_LABEL = 'Phase 2 (2025-26)';

    public const P3_LABEL = 'Phase 3 (2026-27)';

    public function __construct(
        private readonly ProgramDeliverablesReportService $officialReport,
        private readonly LegacyServiceNameNormalizer $normalizer,
        private readonly LegacyApplicationServiceCaseSupport $legacyCases,
    ) {}

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     pipeline: list<array{name: string, phase1: int, phase2: int, phase3: int, total: int}>,
     *     deliverables: list<array{name: string, phase1: int, phase2: int, phase3: int, total: int}>,
     *     official_rows: list<array<string, mixed>>,
     *     crosscheck: list<array{check: string, source_a: string, count_a: int, source_b: string, count_b: int, result: string, gap: int}>,
     *     note: string
     * }
     */
    public function build(?int $districtId = null, ?string $districtSlug = null): array
    {
        $district = $this->resolveDistrict($districtId, $districtSlug);
        $districtId = $district ? (int) $district->id : null;
        $districtName = $district?->name ?? 'All districts';
        $fy = FiscalYear::phase3Default();
        $generatedAt = now()->timezone(config('app.timezone'))->format('d M Y, g:i A T');

        $deliverables = [];
        $p1 = $this->phase1Counts($district, $deliverables);
        $p2 = $this->phase2Counts($district, $deliverables);
        $p3 = $this->phase3Counts($districtId, $fy, $deliverables);

        $official = $this->officialRows($fy, $districtId);
        $officialBySerial = [];
        foreach ($official as $row) {
            if (($row['row_type'] ?? '') === 'leaf') {
                $officialBySerial[(string) ($row['serial'] ?? '')] = $row;
            }
        }
        $officialCfa = (int) ($officialBySerial['1.1']['achievement'] ?? 0);
        $officialOnboard = (int) ($officialBySerial['2.1']['achievement'] ?? 0);

        $officialNameByKey = $this->officialNameKeys();
        foreach ($deliverables as $k => &$item) {
            if (isset($officialNameByKey[$k])) {
                $item['name'] = $officialNameByKey[$k];
            }
        }
        unset($item);

        uasort($deliverables, static function (array $a, array $b): int {
            $ta = $a['phase1'] + $a['phase2'] + $a['phase3'];
            $tb = $b['phase1'] + $b['phase2'] + $b['phase3'];
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $deliverableRows = [];
        foreach ($deliverables as $item) {
            $total = $item['phase1'] + $item['phase2'] + $item['phase3'];
            if ($total === 0) {
                continue;
            }
            $deliverableRows[] = [
                'name' => $item['name'],
                'phase1' => $item['phase1'],
                'phase2' => $item['phase2'],
                'phase3' => $item['phase3'],
                'total' => $total,
            ];
        }

        $pipeline = [
            [
                'name' => 'Call for Application (CFA)',
                'phase1' => $p1['cfa'],
                'phase2' => $p2['cfa'],
                'phase3' => $p3['cfa_native'],
                'total' => $p1['cfa'] + $p2['cfa'] + $p3['cfa_native'],
            ],
            [
                'name' => 'Incubatees Onboarded',
                'phase1' => $p1['onboarded'],
                'phase2' => $p2['onboarded'],
                'phase3' => $p3['onboarded_locked'],
                'total' => $p1['onboarded'] + $p2['onboarded'] + $p3['onboarded_locked'],
            ],
        ];

        $match = static fn (int $a, int $b): string => $a === $b ? 'OK' : 'CHECK';
        $cross = [];
        $this->pushCross($cross, self::P1_LABEL.' CFA', 'Phase 1 list query', $p1['cfa'], 'tblapplication raw', $p1['cfa_raw'], $match);
        $this->pushCross($cross, self::P1_LABEL.' Onboarded', 'onboard=yes on list', $p1['onboarded'], 'tblapplication onboard=yes', $p1['onboarded_raw'], $match);
        $this->pushCross($cross, self::P2_LABEL.' CFA', 'Phase 2 district list', $p2['cfa'], 'rbi latest details raw', $p2['cfa_raw'], $match);
        $this->pushCross($cross, self::P2_LABEL.' Onboarded', 'oa.status not empty on list', $p2['onboarded'], 'raw oa.status not empty', $p2['onboarded_raw'], $match);
        $this->pushCross($cross, self::P3_LABEL.' CFA', 'Official deliverables 1.1', $officialCfa, 'cfa_submissions FY + district', $p3['cfa_fy'], $match);
        $this->pushCross($cross, self::P3_LABEL.' Onboarded', 'Official deliverables 2.1', $officialOnboard, 'locked batches from 1 Apr 2026', $p3['onboarded_locked_fy'], $match);
        $this->pushCross($cross, self::P3_LABEL.' approved service cases', 'Grouped service_cases', $p3['cases'], 'service_cases approved in scope', $p3['cases_raw'], $match);

        $note = self::P3_LABEL.' official CFA = '.$officialCfa
            .'. Native Phase 3 CFA (not mirrored P1/P2) = '.$p3['cfa_native']
            .'. All cfa_submissions in scope (any source) = '.$p3['cfa_all']
            .'. Locked onboarded all-time = '.$p3['onboarded_locked']
            .'; official from 1 Apr 2026 = '.$officialOnboard.'.';

        return [
            'meta' => [
                'title' => $districtName.' — full progress (all phases)',
                'district' => $districtName,
                'district_id' => $districtId,
                'district_slug' => $district?->slug ?? 'all',
                'as_of' => $generatedAt,
                'fiscal_year' => $fy?->code ?? $fy?->name ?? 'FY 2026-27',
                'rules' => [
                    self::P1_LABEL.' = ukrbiin_rbi (tblapplication).',
                    self::P2_LABEL.' = rbiphase2.',
                    self::P3_LABEL.' CFA/Onboarded = native Phase 3 (not mirrored P1/P2).',
                    'Deliverable rows are delivery counts (one person can have more than one).',
                ],
            ],
            'pipeline' => $pipeline,
            'deliverables' => $deliverableRows,
            'official_rows' => $official,
            'crosscheck' => $cross,
            'note' => $note,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    public function writeToPath(array $pack, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Cannot create export directory: '.$dir);
        }

        $meta = $pack['meta'] ?? [];
        $headers = ['S.N.', 'Item', self::P1_LABEL, self::P2_LABEL, self::P3_LABEL, 'Total'];

        $overall = [
            [(string) ($meta['title'] ?? 'Full progress')],
            ['Generated', (string) ($meta['as_of'] ?? '')],
            ['District', (string) ($meta['district'] ?? '')],
            ['Rule', implode(' ', $meta['rules'] ?? [])],
            [],
            ['Pipeline'],
            $headers,
        ];
        $sn = 1;
        foreach ($pack['pipeline'] ?? [] as $row) {
            $overall[] = [$sn, $row['name'], $row['phase1'], $row['phase2'], $row['phase3'], $row['total']];
            $sn++;
        }
        $overall[] = [];
        $overall[] = ['All deliverable / service counts'];
        $overall[] = $headers;
        $sn = 1;
        $sum1 = $sum2 = $sum3 = 0;
        foreach ($pack['deliverables'] ?? [] as $row) {
            $overall[] = [$sn, $row['name'], $row['phase1'], $row['phase2'], $row['phase3'], $row['total']];
            $sum1 += (int) $row['phase1'];
            $sum2 += (int) $row['phase2'];
            $sum3 += (int) $row['phase3'];
            $sn++;
        }
        $overall[] = ['', 'All deliverable total', $sum1, $sum2, $sum3, $sum1 + $sum2 + $sum3];
        $overall[] = [];
        $overall[] = [self::P3_LABEL.' note', (string) ($pack['note'] ?? '')];

        $official = [
            ['Official program deliverables ('.((string) ($meta['fiscal_year'] ?? 'FY 2026-27')).')'],
            ['Same numbers as Admin → Deliverables. Achievement from 1 Apr 2026. District: '.((string) ($meta['district'] ?? ''))],
            [],
            ['S.N.', 'Indicator', 'Type', 'Spoke / Hub / State', 'Target', 'Achievement', 'Achievement %'],
        ];
        foreach ($pack['official_rows'] ?? [] as $row) {
            $isHeading = in_array($row['row_type'] ?? '', ['pillar', 'subcategory'], true);
            $pct = $row['achievement_pct'] ?? null;
            $official[] = [
                $row['serial'] ?? '',
                $row['name'] ?? '',
                $isHeading ? '' : ($row['indicator_type'] ?? ''),
                $isHeading ? '' : ($row['level'] ?? ''),
                $isHeading ? '' : ($row['target'] ?? ''),
                $isHeading ? '' : ($row['achievement'] ?? 0),
                $isHeading ? '' : ($pct !== null ? $pct.'%' : ''),
            ];
        }

        $cross = [
            ['Crosscheck'],
            ['OK = both sources same. CHECK = list query vs raw SQL should match.'],
            [],
            ['Check', 'Source A', 'Count A', 'Source B', 'Count B', 'Result', 'Gap'],
        ];
        foreach ($pack['crosscheck'] ?? [] as $row) {
            $cross[] = [
                $row['check'], $row['source_a'], $row['count_a'],
                $row['source_b'], $row['count_b'], $row['result'], $row['gap'],
            ];
        }

        (new SimpleXlsxWriter)
            ->addSheet('Overall', $overall)
            ->addSheet('Official MIS FY 2026-27', $official)
            ->addSheet('Crosscheck', $cross)
            ->save($absolutePath);
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
     * @param  array<string, array{name: string, phase1: int, phase2: int, phase3: int}>  $deliverables
     * @return array{cfa: int, onboarded: int, cfa_raw: int, onboarded_raw: int, deliveries: int}
     */
    private function phase1Counts(?District $district, array &$deliverables): array
    {
        $empty = ['cfa' => 0, 'onboarded' => 0, 'cfa_raw' => 0, 'onboarded_raw' => 0, 'deliveries' => 0];
        if (! $this->legacyPhase1Ready()) {
            return $empty;
        }

        $query = LegacyPhase1ListQuery::listQuery();
        if ($district) {
            LegacyPhase1DistrictResolver::applyDistrictFilter($query, $district->name);
        }
        $cfa = (int) (clone $query)->count();
        $onboarded = LegacyPhase1DistrictResolver::countOnboarded(clone $query);

        $appsQuery = DB::connection('legacy_phase1')->table('tblapplication');
        if ($district) {
            $keys = LegacyPhase1DistrictResolver::legacyKeysForDistrict($district->name);
            $appsQuery->whereIn(DB::raw('LOWER(TRIM(FatherName))'), $keys);
        }
        $apps = $appsQuery->get();
        $cfaRaw = $apps->count();
        $onboardedRaw = $apps->filter(
            fn ($row): bool => mb_strtolower(trim((string) ($row->onboard ?? ''))) === 'yes'
        )->count();

        $ids = [];
        $appNoById = [];
        foreach ($apps as $row) {
            $id = (int) ($row->ID ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $appNoById[$id] = trim((string) ($row->ApplicationNumber ?? ''));
        }

        $childById = $this->phase1ChildServices($appNoById);
        $jitById = $this->phase1JitServices($ids);
        $fields = config('legacy_phase1.service_fields', []);
        $deliveries = 0;

        foreach ($apps as $row) {
            $id = (int) ($row->ID ?? 0);
            $child = $childById[$id] ?? [];
            $items = $child !== [] ? $child : [];
            if ($child === []) {
                $arr = (array) $row;
                foreach ($fields as $field) {
                    $extracted = $this->phase1FlagService($arr, $field);
                    if ($extracted !== null) {
                        $items[] = $extracted;
                    }
                }
            }
            foreach ($jitById[$id] ?? [] as $jit) {
                $items[] = $jit;
            }
            foreach ($items as $item) {
                $resolved = $this->normalizer->resolve($item['label'], 'Phase 1', $item['detail'] ?? null);
                $this->addCount($deliverables, $resolved['label'], 'phase1');
                $deliveries++;
            }
        }

        return [
            'cfa' => $cfa,
            'onboarded' => $onboarded,
            'cfa_raw' => $cfaRaw,
            'onboarded_raw' => $onboardedRaw,
            'deliveries' => $deliveries,
        ];
    }

    /**
     * @param  array<string, array{name: string, phase1: int, phase2: int, phase3: int}>  $deliverables
     * @return array{cfa: int, onboarded: int, cfa_raw: int, onboarded_raw: int, deliveries: int}
     */
    private function phase2Counts(?District $district, array &$deliverables): array
    {
        $empty = ['cfa' => 0, 'onboarded' => 0, 'cfa_raw' => 0, 'onboarded_raw' => 0, 'deliveries' => 0];
        if (! $this->legacyPhase2Ready()) {
            return $empty;
        }

        try {
            $query = $district
                ? LegacyPhase2ListQuery::districtListQuery($district->name)
                : LegacyPhase2ListQuery::latestApplicantListQuery();
        } catch (\Throwable) {
            return $empty;
        }

        $cfa = (int) (clone $query)->count();
        $onboarded = LegacyPhase2DistrictResolver::countOnboarded(clone $query);
        $appIds = [];
        foreach ((clone $query)->pluck('application_id') as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $appIds[$id] = true;
            }
        }

        $deliveries = 0;
        $ids = array_keys($appIds);
        if ($ids !== [] && Schema::connection('legacy')->hasTable('rbi_services_assigned')) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $rows = DB::connection('legacy')
                    ->table('rbi_services_assigned')
                    ->whereIn('application_id', $chunk)
                    ->get(['service_name', 'category']);
                foreach ($rows as $svc) {
                    $label = trim((string) ($svc->service_name ?? ''));
                    if ($this->isPlaceholder($label)) {
                        continue;
                    }
                    $resolved = $this->normalizer->resolve(
                        $label,
                        'Phase 2',
                        trim((string) ($svc->category ?? '')) ?: null,
                    );
                    $this->addCount($deliverables, $resolved['label'], 'phase2');
                    $deliveries++;
                }
            }
        }

        return [
            'cfa' => $cfa,
            'onboarded' => $onboarded,
            'cfa_raw' => $cfa,
            'onboarded_raw' => $onboarded,
            'deliveries' => $deliveries,
        ];
    }

    /**
     * @param  array<string, array{name: string, phase1: int, phase2: int, phase3: int}>  $deliverables
     * @return array{cfa_all: int, cfa_native: int, cfa_fy: int, onboarded_locked: int, onboarded_locked_fy: int, cases: int, cases_raw: int}
     */
    private function phase3Counts(?int $districtId, ?FiscalYear $fy, array &$deliverables): array
    {
        $cfaQuery = DB::table('cfa_submissions');
        if ($districtId) {
            $cfaQuery->where('district_id', $districtId);
        }
        $cfaAll = (int) (clone $cfaQuery)->count();
        $cfaNative = $cfaAll;
        if (Schema::hasColumn('cfa_submissions', 'source')) {
            $legacySources = "LOWER(TRIM(COALESCE(source,''))) NOT IN ('legacy_phase1','rbiphase1','legacy_phase2','rbiphase2')";
            $cfaNative = (int) (clone $cfaQuery)->whereRaw($legacySources)->count();
        }
        $cfaFy = 0;
        if ($fy && Schema::hasColumn('cfa_submissions', 'fiscal_year_id')) {
            $cfaFy = (int) (clone $cfaQuery)->where('fiscal_year_id', $fy->id)->count();
        }

        $onboardedLocked = 0;
        $onboardedLockedFy = 0;
        if (Schema::hasTable('onboarding_batch_cfa') && Schema::hasTable('onboarding_batches')) {
            $ob = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at');
            if ($districtId) {
                $ob->where('cs.district_id', $districtId);
            }
            $onboardedLocked = (int) (clone $ob)->count();
            $floor = (string) config('program_deliverables.phase3_floor_date', '2026-04-01');
            $onboardedLockedFy = (int) (clone $ob)->where('ob.onboarding_date', '>=', $floor)->count();
        }

        $cases = 0;
        if (Schema::hasTable('service_cases') && Schema::hasTable('services')) {
            $caseQuery = DB::table('service_cases as sc')
                ->join('services as s', 's.id', '=', 'sc.service_id')
                ->leftJoin('deliverables as d', 'd.id', '=', 's.deliverable_id')
                ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
                ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);
            if ($districtId) {
                $this->legacyCases->applyAchievementDistrictScopeToServiceCaseQuery($caseQuery, [$districtId]);
            }
            $rows = $caseQuery
                ->selectRaw('s.name as service_name, d.name as deliverable_name, COUNT(*) as total')
                ->groupBy('s.name', 'd.name')
                ->get();
            foreach ($rows as $row) {
                $label = trim((string) ($row->deliverable_name ?: $row->service_name));
                $resolved = $this->normalizer->resolve($label, 'Phase 3');
                $n = (int) $row->total;
                $cases += $n;
                $this->addCount($deliverables, $resolved['label'], 'phase3', $n);
            }
        }

        return [
            'cfa_all' => $cfaAll,
            'cfa_native' => $cfaNative,
            'cfa_fy' => $cfaFy,
            'onboarded_locked' => $onboardedLocked,
            'onboarded_locked_fy' => $onboardedLockedFy,
            'cases' => $cases,
            'cases_raw' => $cases,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function officialRows(?FiscalYear $fy, ?int $districtId): array
    {
        $filter = new ProgramDeliverablesFilter(
            fiscalYearId: $fy?->id,
            districtId: $districtId,
            month: null,
            year: null,
            dateFrom: null,
            dateTo: null,
        );
        $scope = new ProgramDeliverablesScope('state_admin', null, null, true);

        return $this->officialReport->build($filter, $scope)['rows'] ?? [];
    }

    /**
     * @param  array<int, string>  $appNoById
     * @return array<int, list<array{label: string, detail: ?string}>>
     */
    private function phase1ChildServices(array $appNoById): array
    {
        if ($appNoById === [] || ! Schema::connection('legacy_phase1')->hasTable('services')) {
            return [];
        }

        $idByNo = [];
        foreach ($appNoById as $id => $no) {
            if ($no !== '') {
                $idByNo[$no] = $id;
            }
        }

        $out = [];
        foreach (array_chunk(array_keys($idByNo), 500) as $chunk) {
            $rows = DB::connection('legacy_phase1')
                ->table('services')
                ->whereIn('ApplicationNumber', $chunk)
                ->get(['ApplicationNumber', 'servicename', 'description', 'other']);
            foreach ($rows as $row) {
                $id = (int) ($idByNo[trim((string) $row->ApplicationNumber)] ?? 0);
                $label = trim((string) ($row->description ?? ''));
                if ($id <= 0 || $this->isPlaceholder($label)) {
                    continue;
                }
                $detail = trim((string) ($row->other ?? ''));
                if ($detail === '' || $this->isPlaceholder($detail)) {
                    $detail = trim((string) ($row->servicename ?? ''));
                }
                $out[$id][] = ['label' => $label, 'detail' => $this->isPlaceholder($detail) ? null : $detail];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{label: string, detail: ?string}>>
     */
    private function phase1JitServices(array $ids): array
    {
        if ($ids === [] || ! Schema::connection('legacy_phase1')->hasTable('rbi_services_assigned_jit')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = DB::connection('legacy_phase1')
                ->table('rbi_services_assigned_jit')
                ->whereIn('legacy_app_id', $chunk)
                ->get(['legacy_app_id', 'service_name', 'category']);
            foreach ($rows as $row) {
                $id = (int) $row->legacy_app_id;
                $label = trim((string) ($row->service_name ?? ''));
                if ($id <= 0 || $this->isPlaceholder($label)) {
                    continue;
                }
                $out[$id][] = ['label' => $label, 'detail' => trim((string) ($row->category ?? '')) ?: null];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{column?: string, label?: string, type?: string, detail?: string}  $field
     * @return array{label: string, detail: ?string}|null
     */
    private function phase1FlagService(array $row, array $field): ?array
    {
        $column = (string) ($field['column'] ?? '');
        $label = (string) ($field['label'] ?? $column);
        $type = (string) ($field['type'] ?? 'yes');
        $value = $row[$column] ?? null;
        $v = mb_strtolower(trim((string) ($value ?? '')));

        if ($type === 'yes') {
            if (! in_array($v, ['yes', 'y', '1', 'true'], true)) {
                return null;
            }
            $detailCol = (string) ($field['detail'] ?? '');
            $detail = $detailCol !== '' ? trim((string) ($row[$detailCol] ?? '')) : null;

            return ['label' => $label, 'detail' => $detail ?: null];
        }

        if ($type === 'text') {
            $text = trim((string) ($value ?? ''));
            if ($text === '' || in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a'], true)) {
                return null;
            }

            return ['label' => $label, 'detail' => $text];
        }

        return null;
    }

    /**
     * @param  array<string, array{name: string, phase1: int, phase2: int, phase3: int}>  $bag
     */
    private function addCount(array &$bag, string $name, string $phase, int $n = 1): void
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Unmapped / blank';
        }
        $k = $this->keyOf($name);
        if (! isset($bag[$k])) {
            $bag[$k] = ['name' => $name, 'phase1' => 0, 'phase2' => 0, 'phase3' => 0];
        }
        $bag[$k][$phase] += $n;
    }

    private function keyOf(string $name): string
    {
        $k = $this->normalizer->normalizeKey($name);

        return match ($k) {
            'business model canvas bmc', 'bmc', 'bmc support', 'prepared business model canvas' => 'business model canvas',
            'fssai registration renewal', 'fssai registration', 'fssai' => 'fssai',
            'gst', 'gst registration support' => 'gst registration',
            'business formalization', 'business formalisation' => 'business registration',
            'utdb' => 'utdb registration',
            default => $k,
        };
    }

    /**
     * @return array<string, string>
     */
    private function officialNameKeys(): array
    {
        $out = [];
        $walk = function (array $node) use (&$walk, &$out): void {
            $name = trim((string) ($node['name'] ?? ''));
            $type = (string) ($node['row_type'] ?? '');
            if ($type === 'leaf' && $name !== '') {
                $out[$this->keyOf($name)] = $name;
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child);
            }
        };
        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $walk($pillar);
        }

        return $out;
    }

    /**
     * @param  list<array{check: string, source_a: string, count_a: int, source_b: string, count_b: int, result: string, gap: int}>  $cross
     * @param  callable(int, int): string  $match
     */
    private function pushCross(
        array &$cross,
        string $check,
        string $sourceA,
        int $countA,
        string $sourceB,
        int $countB,
        callable $match,
    ): void {
        $cross[] = [
            'check' => $check,
            'source_a' => $sourceA,
            'count_a' => $countA,
            'source_b' => $sourceB,
            'count_b' => $countB,
            'result' => $match($countA, $countB),
            'gap' => $countA - $countB,
        ];
    }

    private function isPlaceholder(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['', '0', '#n/a', 'n/a', 'na', '-'], true);
    }

    private function legacyPhase1Ready(): bool
    {
        try {
            return (string) config('database.connections.legacy_phase1.database', '') !== ''
                && Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyPhase2Ready(): bool
    {
        try {
            return (string) config('database.connections.legacy.database', '') !== ''
                && Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Throwable) {
            return false;
        }
    }
}
