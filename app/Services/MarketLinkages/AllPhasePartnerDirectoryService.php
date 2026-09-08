<?php

namespace App\Services\MarketLinkages;

use App\Models\District;
use App\Models\MarketLinkagePartner;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\MarketLinkagePartnerCatalogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner-centric market linkage directory across Phase 1, Phase 2 and Phase 3.
 */
class AllPhasePartnerDirectoryService
{
    /** @var list<string> */
    public const YEARS = [
        '2020-21', '2021-22', '2022-23', '2023-24', '2024-25', '2025-26', '2026-27',
    ];

    /** @var array<string, string> */
    private const DISTRICT_ALIASES = [
        'almora' => 'Almora',
        'bageshwar' => 'Bageshwar',
        'chamoli' => 'Chamoli',
        'champawat' => 'Champawat',
        'dehradun' => 'Dehradun',
        'doon' => 'Dehradun',
        'haridwar' => 'Haridwar',
        'hardwar' => 'Haridwar',
        'nainital' => 'Nainital',
        'pauri' => 'Pauri Garhwal',
        'pauri garhwal' => 'Pauri Garhwal',
        'pithoragarh' => 'Pithoragarh',
        'rudraprayag' => 'Rudraprayag',
        'tehri' => 'Tehri Garhwal',
        'tehri garhwal' => 'Tehri Garhwal',
        'udham singh nagar' => 'Udham Singh Nagar',
        'udham singh nagr' => 'Udham Singh Nagar',
        'us nagar' => 'Udham Singh Nagar',
        'u s nagar' => 'Udham Singh Nagar',
        'u.s. nagar' => 'Udham Singh Nagar',
        'uttarkashi' => 'Uttarkashi',
        'uttarakashi' => 'Uttarkashi',
    ];

    /** @var list<string> */
    private const SKIP_KEYS = [
        'in process', 'offline', 'local', 'shop', 'store', 'industry',
        'incubatees', 'website', 'offline partner', 'offline connet',
        'offline market linkage', 'other', 'link', 'market linkage',
        'dont have', "don't have", 'n a', 'na',
    ];

    /** @var list<array<string, mixed>>|null */
    private ?array $allLinksMemo = null;

    public function __construct(
        private MarketLinkagePartnerCatalogService $catalog,
    ) {}

    public static function encodeKey(string $key): string
    {
        return str_replace(' ', '-', $key);
    }

    public static function decodeKey(string $encoded): string
    {
        return str_replace('-', ' ', trim(rawurldecode($encoded)));
    }

    /**
     * @return array{district_ids: list<int>|null, district_names: list<string>|null}
     */
    public function scopeForUser(User $user, int $districtIdFilter = 0): array
    {
        $ids = null;
        $names = null;

        if ($user->role === 'district_staff') {
            $id = (int) ($user->district_id ?: 0);
            $ids = $id > 0 ? [$id] : [];
        } elseif ($user->role === 'hub_admin') {
            $hubId = (int) ($user->hub_id ?: 0);
            $ids = $hubId > 0
                ? District::query()->where('hub_id', $hubId)->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];
        }

        if ($districtIdFilter > 0) {
            if ($ids === null) {
                $ids = [$districtIdFilter];
            } else {
                $ids = in_array($districtIdFilter, $ids, true) ? [$districtIdFilter] : [];
            }
        }

        if ($ids !== null) {
            $names = District::query()
                ->whereIn('id', $ids)
                ->pluck('name')
                ->map(fn ($name) => mb_strtolower($this->canonicalDistrict((string) $name)))
                ->filter()
                ->values()
                ->all();
        }

        return [
            'district_ids' => $ids,
            'district_names' => $names,
        ];
    }

    /**
     * @param  array{q?: string, fy?: string, phase?: string, mode?: string, district_ids?: list<int>|null, district_names?: list<string>|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function partners(array $filters = []): array
    {
        $grouped = [];
        foreach ($this->filteredLinks($filters) as $link) {
            $key = (string) $link['partner_key'];
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'key_param' => self::encodeKey($key),
                    'name' => (string) $link['partner_name'],
                    'incubatee_count' => 0,
                    'link_count' => 0,
                    'online' => 0,
                    'offline' => 0,
                    'phases' => [],
                    'years' => [],
                    'districts' => [],
                    'incubatee_keys' => [],
                ];
            } elseif (strlen((string) $link['partner_name']) < strlen((string) $grouped[$key]['name'])) {
                $grouped[$key]['name'] = (string) $link['partner_name'];
            }

            $grouped[$key]['link_count']++;
            $ik = (string) $link['incubatee_key'];
            if ($ik !== '' && ! isset($grouped[$key]['incubatee_keys'][$ik])) {
                $grouped[$key]['incubatee_keys'][$ik] = true;
                $grouped[$key]['incubatee_count']++;
            }
            $mode = (string) $link['mode'];
            if ($mode === 'online') {
                $grouped[$key]['online']++;
            } elseif ($mode === 'offline') {
                $grouped[$key]['offline']++;
            }
            $phase = (string) $link['phase_label'];
            $grouped[$key]['phases'][$phase] = true;
            $fy = (string) $link['fy'];
            if ($fy !== '') {
                $grouped[$key]['years'][$fy] = true;
            }
            $district = trim((string) $link['district']);
            if ($district !== '') {
                $grouped[$key]['districts'][$district] = true;
            }
        }

        $out = [];
        foreach ($grouped as $row) {
            $years = array_keys($row['years']);
            sort($years);
            $phases = array_keys($row['phases']);
            sort($phases);
            $districts = array_keys($row['districts']);
            natcasesort($districts);
            $out[] = [
                'key' => $row['key'],
                'key_param' => $row['key_param'],
                'name' => $row['name'],
                'incubatee_count' => $row['incubatee_count'],
                'link_count' => $row['link_count'],
                'online' => $row['online'],
                'offline' => $row['offline'],
                'phases' => $phases,
                'years' => $years,
                'districts' => array_values($districts),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['incubatee_count'] !== $b['incubatee_count']) {
                return $b['incubatee_count'] <=> $a['incubatee_count'];
            }

            return strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $out;
    }

    /**
     * @param  array{q?: string, fy?: string, phase?: string, mode?: string, district_ids?: list<int>|null, district_names?: list<string>|null}  $filters
     * @return array{partner: array<string, mixed>, links: list<array<string, mixed>}|null
     */
    public function partner(string $key, array $filters = []): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        $filters['partner_key'] = $key;
        $links = $this->filteredLinks($filters);

        $identityFilters = $filters;
        $identityFilters['q'] = '';
        $identityFilters['fy'] = '';
        $identityFilters['phase'] = '';
        $identityFilters['mode'] = '';
        $identityFilters['onboard'] = '';
        $identityFilters['partner_key'] = $key;
        $identity = $this->partners($identityFilters)[0] ?? null;
        if ($identity === null) {
            return null;
        }

        $partner = $identity;
        if ($links !== []) {
            $filtered = $this->partners($filters)[0] ?? null;
            if ($filtered !== null) {
                $partner = $filtered;
                $partner['name'] = $identity['name'];
                $partner['key'] = $identity['key'];
                $partner['key_param'] = $identity['key_param'];
            }
        } else {
            $partner['incubatee_count'] = 0;
            $partner['link_count'] = 0;
            $partner['online'] = 0;
            $partner['offline'] = 0;
            $partner['phases'] = [];
            $partner['years'] = [];
            $partner['districts'] = [];
        }

        usort($links, static function (array $a, array $b): int {
            $name = strcasecmp((string) $a['incubatee_name'], (string) $b['incubatee_name']);
            if ($name !== 0) {
                return $name;
            }

            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return [
            'partner' => $partner,
            'links' => $links,
        ];
    }

    /**
     * @param  array{q?: string, fy?: string, phase?: string, mode?: string, district_ids?: list<int>|null, district_names?: list<string>|null}  $filters
     * @return array{partners: int, incubatees: int, links: int, online: int, offline: int, phase1: int, phase2: int, phase3: int}
     */
    public function stats(array $filters = []): array
    {
        $incubatees = [];
        $stats = [
            'partners' => 0,
            'incubatees' => 0,
            'links' => 0,
            'online' => 0,
            'offline' => 0,
            'phase1' => 0,
            'phase2' => 0,
            'phase3' => 0,
        ];
        $partnerKeys = [];
        foreach ($this->filteredLinks($filters) as $link) {
            $stats['links']++;
            $partnerKeys[(string) $link['partner_key']] = true;
            $ik = (string) $link['incubatee_key'];
            if ($ik !== '') {
                $incubatees[$ik] = true;
            }
            if ($link['mode'] === 'online') {
                $stats['online']++;
            } elseif ($link['mode'] === 'offline') {
                $stats['offline']++;
            }
            $phase = (int) $link['phase'];
            if ($phase === 1) {
                $stats['phase1']++;
            } elseif ($phase === 2) {
                $stats['phase2']++;
            } elseif ($phase === 3) {
                $stats['phase3']++;
            }
        }
        $stats['partners'] = count($partnerKeys);
        $stats['incubatees'] = count($incubatees);

        return $stats;
    }

    /**
     * @param  array{q?: string, fy?: string, phase?: string, mode?: string, partner_key?: string, district_ids?: list<int>|null, district_names?: list<string>|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function filteredLinks(array $filters = []): array
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $fy = trim((string) ($filters['fy'] ?? ''));
        $phase = trim((string) ($filters['phase'] ?? ''));
        $mode = strtolower(trim((string) ($filters['mode'] ?? '')));
        $partnerKey = trim((string) ($filters['partner_key'] ?? ''));
        $districtIds = $filters['district_ids'] ?? null;
        $districtNames = $filters['district_names'] ?? null;

        if (is_array($districtIds) && $districtIds === []) {
            return [];
        }

        $out = [];
        foreach ($this->allLinks() as $link) {
            if ($partnerKey !== '' && (string) $link['partner_key'] !== $partnerKey) {
                continue;
            }
            if ($fy !== '' && (string) $link['fy'] !== $fy) {
                continue;
            }
            if ($phase !== '' && (string) $link['phase'] !== $phase) {
                continue;
            }
            if ($mode !== '' && (string) $link['mode'] !== $mode) {
                continue;
            }
            $onboard = trim((string) ($filters['onboard'] ?? ''));
            if ($onboard === 'onboarded' && (string) ($link['onboard_status'] ?? '') !== 'Onboarded') {
                continue;
            }
            if ($onboard === 'not_onboarded' && (string) ($link['onboard_status'] ?? '') !== 'Not onboarded') {
                continue;
            }
            if (is_array($districtIds) && (int) $link['phase'] === 3) {
                $did = (int) ($link['district_id'] ?? 0);
                if ($did > 0 && ! in_array($did, $districtIds, true)) {
                    continue;
                }
                if ($did < 1 && is_array($districtNames)) {
                    $dname = mb_strtolower((string) $link['district']);
                    if ($dname === '' || ! in_array($dname, $districtNames, true)) {
                        continue;
                    }
                }
            } elseif (is_array($districtNames) && (int) $link['phase'] !== 3) {
                $dname = mb_strtolower((string) $link['district']);
                if ($dname === '' || ! in_array($dname, $districtNames, true)) {
                    continue;
                }
            }

            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [
                    $link['partner_name'],
                    $link['incubatee_name'],
                    $link['application_no'],
                    $link['district'],
                    $link['phone'],
                ]));
                if (! str_contains($hay, $q)) {
                    continue;
                }
            }

            $out[] = $link;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allLinks(): array
    {
        if ($this->allLinksMemo !== null) {
            return $this->allLinksMemo;
        }

        $seen = [];
        $links = [];

        foreach ($this->phase1Links() as $link) {
            $this->pushLink($links, $seen, $link);
        }
        foreach ($this->phase2Links() as $link) {
            $this->pushLink($links, $seen, $link);
        }
        foreach ($this->phase3Links() as $link) {
            $this->pushLink($links, $seen, $link);
        }

        $this->hydrateOnboardStatus($links);

        return $this->allLinksMemo = $links;
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @param  array<string, true>  $seen
     * @param  array<string, mixed>  $link
     */
    private function pushLink(array &$links, array &$seen, array $link): void
    {
        $dedupe = implode('|', [
            $link['partner_key'],
            $link['incubatee_key'],
            $link['fy'],
            $link['phase'],
            $link['mode'],
            $link['date'],
        ]);
        if (isset($seen[$dedupe])) {
            return;
        }
        $seen[$dedupe] = true;
        $links[] = $link;
    }

    /**
     * @param  list<array<string, mixed>>  $links
     */
    private function hydrateOnboardStatus(array &$links): void
    {
        $appNos = [];
        $cfaIds = [];
        foreach ($links as $link) {
            $appNo = trim((string) ($link['application_no'] ?? ''));
            if ($appNo !== '' && $appNo !== '—') {
                $appNos[$appNo] = true;
            }
            $key = (string) ($link['incubatee_key'] ?? '');
            if (str_starts_with($key, 'c:')) {
                $id = (int) substr($key, 2);
                if ($id > 0) {
                    $cfaIds[$id] = true;
                }
            }
        }

        $onboardedApps = $this->onboardedApplicationNos(array_keys($appNos));
        $onboardedCfas = $this->onboardedCfaSubmissionIds(array_keys($cfaIds));

        foreach ($links as &$link) {
            $appNo = trim((string) ($link['application_no'] ?? ''));
            $cfaId = 0;
            $key = (string) ($link['incubatee_key'] ?? '');
            if (str_starts_with($key, 'c:')) {
                $cfaId = (int) substr($key, 2);
            }
            $isOnboarded = ($appNo !== '' && isset($onboardedApps[$appNo]))
                || ($cfaId > 0 && isset($onboardedCfas[$cfaId]));
            $link['onboard_status'] = $isOnboarded ? 'Onboarded' : 'Not onboarded';
        }
        unset($link);
    }

    /**
     * @param  list<string>  $appNos
     * @return array<string, true>
     */
    private function onboardedApplicationNos(array $appNos): array
    {
        $found = [];
        if ($appNos === []) {
            return $found;
        }

        foreach (array_chunk($appNos, 400) as $chunk) {
            try {
                if (Schema::hasTable('onboarding_batch_cfa')
                    && Schema::hasTable('onboarding_batches')
                    && Schema::hasTable('cfa_submissions')
                ) {
                    $rows = DB::table('onboarding_batch_cfa as obc')
                        ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                        ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
                        ->where('ob.status', 'locked')
                        ->whereNotNull('ob.locked_at')
                        ->whereIn('cs.application_no', $chunk)
                        ->pluck('cs.application_no');
                    foreach ($rows as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
            }

            if ($this->skipLegacyInTests()) {
                continue;
            }

            try {
                if (Schema::connection('legacy')->hasTable('rbi_onboarded_applicants')
                    && Schema::connection('legacy')->hasTable('rbi_applications')
                ) {
                    $query = DB::connection('legacy')
                        ->table('rbi_onboarded_applicants as oa')
                        ->join('rbi_applications as a', 'a.id', '=', 'oa.application_id')
                        ->whereIn('a.application_no', $chunk)
                        ->whereNotNull('oa.application_id');
                    if (Schema::connection('legacy')->hasColumn('rbi_onboarded_applicants', 'status')) {
                        $query->whereNotNull('oa.status')->where('oa.status', '<>', '');
                    }
                    foreach ($query->pluck('a.application_no') as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if ((string) config('database.connections.legacy_phase1.database', '') !== ''
                    && Schema::connection('legacy_phase1')->hasTable('tblapplication')
                    && Schema::connection('legacy_phase1')->hasColumn('tblapplication', 'onboard')
                ) {
                    $rows = DB::connection('legacy_phase1')
                        ->table('tblapplication')
                        ->whereIn('ApplicationNumber', $chunk)
                        ->whereRaw('LOWER(TRIM(onboard)) = ?', ['yes'])
                        ->pluck('ApplicationNumber');
                    foreach ($rows as $appNo) {
                        $appNo = trim((string) $appNo);
                        if ($appNo !== '') {
                            $found[$appNo] = true;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $found;
    }

    /**
     * @param  list<int>  $cfaIds
     * @return array<int, true>
     */
    private function onboardedCfaSubmissionIds(array $cfaIds): array
    {
        $found = [];
        if ($cfaIds === []) {
            return $found;
        }

        try {
            if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
                return $found;
            }
            $rows = DB::table('onboarding_batch_cfa as obc')
                ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
                ->where('ob.status', 'locked')
                ->whereNotNull('ob.locked_at')
                ->whereIn('obc.cfa_submission_id', $cfaIds)
                ->pluck('obc.cfa_submission_id');
            foreach ($rows as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $found[$id] = true;
                }
            }
        } catch (\Throwable) {
        }

        return $found;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase1Links(): array
    {
        if ($this->skipLegacyInTests()) {
            return [];
        }
        try {
            if ((string) config('database.connections.legacy_phase1.database', '') === ''
                || ! Schema::connection('legacy_phase1')->hasTable('tblapplication')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $available = Schema::connection('legacy_phase1')->getColumnListing('tblapplication');
        $wanted = [
            'ID', 'ApplicationNumber', 'FullName', 'FatherName', 'MobileNumber', 'hub',
            'ApplicationDate', 'onboard_date', 'onboarding_date',
            'partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner',
        ];
        $select = array_values(array_intersect($wanted, $available));
        if (! in_array('ApplicationNumber', $select, true)) {
            return [];
        }

        $partnersByAppNo = [];
        if (Schema::connection('legacy_phase1')->hasTable('partner')) {
            foreach (DB::connection('legacy_phase1')->table('partner')->get(['ApplicationNumber', 'partner_name']) as $row) {
                $no = trim((string) ($row->ApplicationNumber ?? ''));
                $name = $this->clean((string) ($row->partner_name ?? ''));
                if ($no === '' || $name === '' || $this->isNo($name)) {
                    continue;
                }
                $partnersByAppNo[$no][] = $name;
            }
        }

        $out = [];
        foreach (DB::connection('legacy_phase1')->table('tblapplication')->select($select)->orderBy('ID')->get() as $row) {
            $resolved = $this->resolvePhase1Year(
                (string) ($row->ApplicationDate ?? ''),
                (string) ($row->onboard_date ?? ''),
                (string) ($row->onboarding_date ?? ''),
            );
            if ($resolved === null) {
                continue;
            }
            [$fy, $dateUsed] = $resolved;
            if (in_array($fy, ['2025-26', '2026-27'], true)) {
                continue;
            }

            $appNo = trim((string) ($row->ApplicationNumber ?? ''));
            $names = $this->extractPhase1PartnerNames($row);
            foreach ($partnersByAppNo[$appNo] ?? [] as $extra) {
                $names[] = $extra;
            }
            $names = array_values(array_unique($names));
            if ($names === []) {
                continue;
            }

            $incubatee = $this->clean((string) ($row->FullName ?? ''));
            $district = $this->canonicalDistrict((string) ($row->FatherName ?? ''));
            $phone = $this->cleanPhone((string) ($row->MobileNumber ?? ''));
            $incubateeKey = $appNo !== '' ? 'p1:'.$appNo : 'p1id:'.(string) ($row->ID ?? '');

            foreach ($names as $rawName) {
                $built = $this->makeLink($rawName, [
                    'incubatee_name' => $incubatee,
                    'application_no' => $appNo,
                    'district' => $district,
                    'phone' => $phone,
                    'block' => '',
                    'mode' => '',
                    'date' => $dateUsed,
                    'fy' => $fy,
                    'phase' => 1,
                    'phase_label' => 'Phase 1',
                    'source' => 'ukrbiin_rbi',
                    'incubatee_key' => $incubateeKey,
                    'district_id' => 0,
                ]);
                if ($built !== null) {
                    $out[] = $built;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase2Links(): array
    {
        if ($this->skipLegacyInTests()) {
            return [];
        }
        try {
            if ((string) config('database.connections.legacy.database', '') === ''
                || ! Schema::connection('legacy')->hasTable('rbi_service_partners')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $query = DB::connection('legacy')->table('rbi_service_partners as sp');
        if (Schema::connection('legacy')->hasTable('rbi_services_assigned')
            && Schema::connection('legacy')->hasTable('rbi_applications')) {
            $query->leftJoin('rbi_services_assigned as sa', 'sa.id', '=', 'sp.service_assigned_id')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'sa.application_id');
            if (Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
                $query->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'sa.application_id');
            }
        }

        $select = ['sp.partner_name', 'sp.partner_type', 'sp.partner_link', 'sp.added_at', 'sp.id'];
        if (Schema::connection('legacy')->hasTable('rbi_applications')) {
            $select[] = 'a.application_no';
        }
        if (Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            $select[] = 'd.applicant_name';
            $select[] = 'd.phone';
            $select[] = 'd.district';
            $select[] = 'd.block';
        }

        $out = [];
        foreach ($query->get($select) as $row) {
            $resolved = $this->yearFromRaw((string) ($row->added_at ?? ''));
            if ($resolved === null) {
                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $appNo = trim((string) ($row->application_no ?? ''));
            $built = $this->makeLink((string) ($row->partner_name ?? ''), [
                'incubatee_name' => $this->clean((string) ($row->applicant_name ?? '')),
                'application_no' => $appNo,
                'district' => $this->canonicalDistrict((string) ($row->district ?? '')),
                'phone' => $this->cleanPhone((string) ($row->phone ?? '')),
                'block' => $this->clean((string) ($row->block ?? '')),
                'mode' => $this->normalizeMode((string) ($row->partner_type ?? '')),
                'date' => $dateUsed,
                'fy' => $fy,
                'link_url' => trim((string) ($row->partner_link ?? '')),
                'phase' => 2,
                'phase_label' => 'Phase 2',
                'source' => 'rbiphase2',
                'incubatee_key' => $appNo !== '' ? 'p2:'.$appNo : 'p2id:'.(string) ($row->id ?? ''),
                'district_id' => 0,
            ]);
            if ($built !== null) {
                $out[] = $built;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phase3Links(): array
    {
        if (! Schema::hasTable('market_linkage_partners') || ! Schema::hasTable('market_linkage_submissions')) {
            return [];
        }

        $query = DB::table('market_linkage_partners as mlp')
            ->join('market_linkage_submissions as mls', 'mls.id', '=', 'mlp.market_linkage_submission_id')
            ->leftJoin('districts as d', 'd.id', '=', 'mls.district_id');
        if (Schema::hasTable('cfa_submissions')) {
            $query->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'mls.cfa_submission_id');
        }
        if (Schema::hasColumn('market_linkage_submissions', 'status')) {
            $query->where('mls.status', ServiceCase::STATUS_APPROVED);
        }

        $select = [
            'mlp.partner_name',
            'mlp.linkage_mode',
            'mlp.linkage_date',
            'mlp.link_url',
            'mls.id as submission_id',
            'mls.application_no',
            'mls.incubatee_name',
            'mls.cfa_submission_id',
            'mls.legacy_application_id',
            'mls.district_id',
            'mls.approved_at',
            'mls.submitted_at',
            'mls.created_at',
            'd.name as district_name',
        ];
        if (Schema::hasTable('cfa_submissions') && Schema::hasColumn('cfa_submissions', 'phone')) {
            $select[] = 'cs.phone';
        }

        $out = [];
        foreach ($query->get($select) as $row) {
            $rawDate = trim((string) ($row->linkage_date ?? ''));
            if ($rawDate === '') {
                $rawDate = (string) ($row->approved_at ?? $row->submitted_at ?? $row->created_at ?? '');
            }
            $resolved = $this->yearFromRaw($rawDate);
            if ($resolved === null) {
                continue;
            }
            [$fy, $dateUsed] = $resolved;
            $appNo = trim((string) ($row->application_no ?? ''));
            $cfaId = (int) ($row->cfa_submission_id ?? 0);
            $legacyId = (int) ($row->legacy_application_id ?? 0);
            $sid = (int) ($row->submission_id ?? 0);
            $incubateeKey = $cfaId > 0 ? 'c:'.$cfaId : ($legacyId > 0 ? 'l:'.$legacyId : ($appNo !== '' ? 'a:'.$appNo : 's:'.$sid));

            $built = $this->makeLink((string) ($row->partner_name ?? ''), [
                'incubatee_name' => $this->clean((string) ($row->incubatee_name ?? '')),
                'application_no' => $appNo,
                'district' => $this->canonicalDistrict((string) ($row->district_name ?? '')),
                'phone' => $this->cleanPhone((string) ($row->phone ?? '')),
                'block' => '',
                'mode' => $this->normalizeMode((string) ($row->linkage_mode ?? '')),
                'date' => $dateUsed,
                'fy' => $fy,
                'link_url' => trim((string) ($row->link_url ?? '')),
                'phase' => 3,
                'phase_label' => 'Phase 3',
                'source' => 'muy',
                'incubatee_key' => $incubateeKey,
                'district_id' => (int) ($row->district_id ?? 0),
                'submission_id' => $sid,
            ]);
            if ($built !== null) {
                $out[] = $built;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function makeLink(string $rawName, array $base): ?array
    {
        $rawName = $this->clean($rawName);
        if ($rawName === '' || $this->isNo($rawName)) {
            return null;
        }
        $key = $this->catalog->normalizePartnerKey($rawName);
        if ($key === '' || in_array($key, self::SKIP_KEYS, true) || strlen($key) < 2) {
            return null;
        }
        $display = $this->catalog->displayLabelFor($rawName);
        if ($display === '') {
            $display = $rawName;
        }
        $url = trim((string) ($base['link_url'] ?? ''));
        $mode = (string) ($base['mode'] ?? '');

        return [
            'partner_key' => $key,
            'partner_name' => $display,
            'incubatee_name' => (string) ($base['incubatee_name'] ?? '') ?: '—',
            'application_no' => (string) ($base['application_no'] ?? ''),
            'district' => (string) ($base['district'] ?? ''),
            'district_id' => (int) ($base['district_id'] ?? 0),
            'phone' => (string) ($base['phone'] ?? ''),
            'block' => (string) ($base['block'] ?? ''),
            'mode' => $mode,
            'mode_label' => $mode === 'online' ? 'Online' : ($mode === 'offline' ? 'Offline' : '—'),
            'date' => (string) ($base['date'] ?? ''),
            'fy' => (string) ($base['fy'] ?? ''),
            'link_url' => $url,
            'link_href' => MarketLinkagePartner::clickableHref($url),
            'phase' => (int) ($base['phase'] ?? 0),
            'phase_label' => (string) ($base['phase_label'] ?? ''),
            'source' => (string) ($base['source'] ?? ''),
            'incubatee_key' => (string) ($base['incubatee_key'] ?? ''),
            'submission_id' => (int) ($base['submission_id'] ?? 0),
            'onboard_status' => 'Not onboarded',
        ];
    }

    private function extractPhase1PartnerNames(object $row): array
    {
        $names = [];
        foreach (['partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'] as $col) {
            $raw = $this->clean((string) ($row->{$col} ?? ''));
            if ($raw === '' || $this->isNo($raw)) {
                continue;
            }
            if (stripos($raw, 'offline') !== false && strlen($raw) < 12) {
                continue;
            }
            foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                $part = $this->clean($part);
                if ($part !== '' && ! $this->isNo($part)) {
                    $names[] = $part;
                }
            }
        }

        return $names;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function resolvePhase1Year(string $applicationDate, string $onboardDate, string $onboardingDate): ?array
    {
        $app = $this->parseDate($applicationDate);
        if ($app !== null) {
            $fy = $this->fyLabel($app);
            if (in_array($fy, self::YEARS, true)) {
                return [$fy, $app->toDateString()];
            }
        }
        foreach ([$onboardDate, $onboardingDate] as $raw) {
            $resolved = $this->yearFromRaw($raw);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function yearFromRaw(string $raw): ?array
    {
        $d = $this->parseDate($raw);
        if ($d === null) {
            return null;
        }
        $fy = $this->fyLabel($d);
        if (! in_array($fy, self::YEARS, true)) {
            return null;
        }

        return [$fy, $d->toDateString()];
    }

    private function parseDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if ($y < 2020 || $y > 2027 || ! checkdate($mo, $d, $y)) {
                return null;
            }

            return Carbon::create($y, $mo, $d, 0, 0, 0, 'Asia/Kolkata');
        }

        try {
            $d = Carbon::parse($raw, 'Asia/Kolkata');
            if ($d->year < 2020 || $d->year > 2027) {
                return null;
            }

            return $d->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fyLabel(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    private function canonicalDistrict(string $raw): string
    {
        $norm = mb_strtolower(trim(str_replace(['_', '-'], ' ', $raw)));
        $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? $norm);
        if ($norm === '') {
            return '';
        }
        if (isset(self::DISTRICT_ALIASES[$norm])) {
            return self::DISTRICT_ALIASES[$norm];
        }

        return $this->clean($raw);
    }

    private function normalizeMode(string $value): string
    {
        $v = strtolower(trim($value));

        return match ($v) {
            MarketLinkageSubmission::LINKAGE_ONLINE, 'online' => 'online',
            MarketLinkageSubmission::LINKAGE_OFFLINE, 'offline' => 'offline',
            default => '',
        };
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function cleanPhone(string $value): string
    {
        $value = $this->clean($value);
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 8 ? $value : '';
    }

    private function skipLegacyInTests(): bool
    {
        return app()->environment('testing') && config('database.default') === 'sqlite';
    }

    private function isNo(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a', '#n/a', '-'], true);
    }
}
