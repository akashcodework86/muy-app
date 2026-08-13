<?php

namespace App\Services;

use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-phase interventions for batch member rows (Phase 1 tblapplication +
 * services + JIT assignments, Phase 2 rbi_services_assigned, Phase 3
 * service_cases + market_linkage_submissions).
 */
class BatchMemberInterventionsSummaryService
{
    /**
     * @param  list<array{
     *     id: int,
     *     application_no: string,
     *     phone: string,
     *     source?: string,
     *     legacy_application_id?: int,
     *     legacy_phase1_id?: int
     * }>  $members
     * @return list<array<string, mixed>>
     */
    public function attachServices(array $members): array
    {
        if ($members === []) {
            return [];
        }

        $keys = $this->resolveKeys($members);

        $phase3ByCfa = $this->loadPhase3($keys['cfa_ids'], $keys['legacy_app_by_cfa']);
        $marketLinkageByCfa = $this->loadMarketLinkages($keys['cfa_ids'], $keys['legacy_app_by_cfa']);
        $phase2ByLegacyApp = $this->loadPhase2($keys['all_legacy_app_ids']);
        $phase1ById = $this->loadPhase1($keys['all_phase1_ids']);

        return array_map(function (array $member) use ($phase3ByCfa, $marketLinkageByCfa, $phase2ByLegacyApp, $phase1ById, $keys): array {
            $cfaId = (int) ($member['id'] ?? 0);
            $legacyAppId = (int) ($keys['legacy_app_by_cfa'][$cfaId] ?? 0);
            $phase1Id = (int) ($keys['phase1_by_cfa'][$cfaId] ?? 0);

            $services = array_merge(
                $phase1ById[$phase1Id] ?? [],
                $phase2ByLegacyApp[$legacyAppId] ?? [],
                $phase3ByCfa[$cfaId] ?? [],
                $marketLinkageByCfa[$cfaId] ?? [],
            );

            $member['services'] = $services;

            return $member;
        }, $members);
    }

    /**
     * @param  list<array{id: int, application_no: string, phone: string, legacy_application_id?: int, legacy_phase1_id?: int}>  $members
     * @return array{
     *     cfa_ids: list<int>,
     *     legacy_app_by_cfa: array<int, int>,
     *     all_legacy_app_ids: list<int>,
     *     phase1_by_cfa: array<int, int>,
     *     all_phase1_ids: list<int>
     * }
     */
    private function resolveKeys(array $members): array
    {
        $cfaIds = [];
        $phase2PhoneToCfa = [];
        $phase2AppNoToCfa = [];
        $phase1PhoneToCfa = [];
        $phase1AppNoToCfa = [];
        $legacyAppByCfa = [];
        $phase1ByCfa = [];

        foreach ($members as $member) {
            $cfaId = (int) ($member['id'] ?? 0);
            if ($cfaId <= 0) {
                continue;
            }

            $source = strtolower(trim((string) ($member['source'] ?? '')));
            $isPhase1Source = in_array($source, ['phase1', 'legacy_phase1'], true);
            $isPhase2Source = in_array($source, ['phase2', 'legacy_phase2'], true);
            if (! in_array($source, ['phase1', 'phase2', 'legacy_phase1', 'legacy_phase2'], true)) {
                $cfaIds[] = $cfaId;
            }

            $legacyAppId = (int) ($member['legacy_application_id'] ?? 0);
            if ($legacyAppId > 0) {
                $legacyAppByCfa[$cfaId] = $legacyAppId;
            }

            $phase1Id = (int) ($member['legacy_phase1_id'] ?? 0);
            if ($phase1Id > 0) {
                $phase1ByCfa[$cfaId] = $phase1Id;
            }

            $phone = $this->normalizePhone((string) ($member['phone'] ?? ''));
            if ($phone !== '') {
                if ($isPhase1Source) {
                    $phase1PhoneToCfa[$phone][] = $cfaId;
                } elseif ($isPhase2Source) {
                    $phase2PhoneToCfa[$phone][] = $cfaId;
                }
            }

            $appNo = trim((string) ($member['application_no'] ?? ''));
            if ($appNo !== '') {
                if ($isPhase1Source) {
                    $phase1AppNoToCfa[$appNo][] = $cfaId;
                } elseif ($isPhase2Source) {
                    $phase2AppNoToCfa[$appNo][] = $cfaId;
                }
            }
        }

        $cfaIds = array_values(array_unique($cfaIds));

        if ($this->legacyPhase2Available()) {
            $this->fillLegacyAppIdsFromPhase2($phase2PhoneToCfa, $phase2AppNoToCfa, $legacyAppByCfa);
        }

        if ($this->legacyPhase1Available()) {
            $this->fillPhase1Ids($phase1PhoneToCfa, $phase1AppNoToCfa, $phase1ByCfa);
        }

        return [
            'cfa_ids' => $cfaIds,
            'legacy_app_by_cfa' => $legacyAppByCfa,
            'all_legacy_app_ids' => array_values(array_unique(array_filter(
                array_map('intval', array_values($legacyAppByCfa)),
                fn (int $id) => $id > 0
            ))),
            'phase1_by_cfa' => $phase1ByCfa,
            'all_phase1_ids' => array_values(array_unique(array_filter(
                array_map('intval', array_values($phase1ByCfa)),
                fn (int $id) => $id > 0
            ))),
        ];
    }

    /**
     * @param  array<string, list<int>>  $phoneToCfa
     * @param  array<string, list<int>>  $appNoToCfa
     * @param  array<int, int>  $legacyAppByCfa
     */
    private function fillLegacyAppIdsFromPhase2(array $phoneToCfa, array $appNoToCfa, array &$legacyAppByCfa): void
    {
        $phones = array_keys($phoneToCfa);
        if ($phones !== []) {
            $byPhone = DB::connection('legacy')
                ->table('rbi_applicant_details')
                ->whereIn('phone', $phones)
                ->orderByDesc('application_id')
                ->get(['application_id', 'phone']);

            $appIdByPhone = [];
            foreach ($byPhone as $row) {
                $phone = $this->normalizePhone((string) ($row->phone ?? ''));
                $appId = (int) ($row->application_id ?? 0);
                if ($phone === '' || $appId <= 0 || isset($appIdByPhone[$phone])) {
                    continue;
                }
                $appIdByPhone[$phone] = $appId;
            }

            foreach ($appIdByPhone as $phone => $appId) {
                foreach ($phoneToCfa[$phone] ?? [] as $cfaId) {
                    if (! isset($legacyAppByCfa[$cfaId]) || $legacyAppByCfa[$cfaId] <= 0) {
                        $legacyAppByCfa[$cfaId] = $appId;
                    }
                }
            }
        }

        $appNos = array_keys($appNoToCfa);
        if ($appNos !== []) {
            $byAppNo = DB::connection('legacy')
                ->table('rbi_applications')
                ->whereIn('application_no', $appNos)
                ->orderByDesc('id')
                ->get(['id', 'application_no']);

            $appIdByNo = [];
            foreach ($byAppNo as $row) {
                $appNo = trim((string) ($row->application_no ?? ''));
                $appId = (int) ($row->id ?? 0);
                if ($appNo === '' || $appId <= 0 || isset($appIdByNo[$appNo])) {
                    continue;
                }
                $appIdByNo[$appNo] = $appId;
            }

            foreach ($appIdByNo as $appNo => $appId) {
                foreach ($appNoToCfa[$appNo] ?? [] as $cfaId) {
                    if (! isset($legacyAppByCfa[$cfaId]) || $legacyAppByCfa[$cfaId] <= 0) {
                        $legacyAppByCfa[$cfaId] = $appId;
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, list<int>>  $phoneToCfa
     * @param  array<string, list<int>>  $appNoToCfa
     * @param  array<int, int>  $phase1ByCfa
     */
    private function fillPhase1Ids(array $phoneToCfa, array $appNoToCfa, array &$phase1ByCfa): void
    {
        $appNos = array_keys($appNoToCfa);
        if ($appNos !== []) {
            $idByAppNo = [];
            foreach (array_chunk($appNos, 500) as $appNoChunk) {
                $byAppNo = DB::connection('legacy_phase1')
                    ->table('tblapplication')
                    ->whereIn('ApplicationNumber', $appNoChunk)
                    ->orderByDesc('ID')
                    ->get(['ID', 'ApplicationNumber']);

                foreach ($byAppNo as $row) {
                    $appNo = trim((string) ($row->ApplicationNumber ?? ''));
                    $id = (int) ($row->ID ?? 0);
                    if ($appNo === '' || $id <= 0 || isset($idByAppNo[$appNo])) {
                        continue;
                    }
                    $idByAppNo[$appNo] = $id;
                }
            }

            foreach ($idByAppNo as $appNo => $id) {
                foreach ($appNoToCfa[$appNo] ?? [] as $cfaId) {
                    if (! isset($phase1ByCfa[$cfaId]) || $phase1ByCfa[$cfaId] <= 0) {
                        $phase1ByCfa[$cfaId] = $id;
                    }
                }
            }
        }

        $phones = array_keys($phoneToCfa);
        if ($phones === []) {
            return;
        }

        $digits = array_map(fn (string $p) => preg_replace('/\D/', '', $p), $phones);
        $candidates = array_values(array_unique(array_filter($digits, fn ($d) => strlen((string) $d) >= 10)));

        if ($candidates === []) {
            return;
        }

        $candidateLookup = array_fill_keys(array_map(
            fn (string $digits): string => substr($digits, -10),
            $candidates,
        ), true);
        $rows = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereNotNull('MobileNumber')
            ->orderByDesc('ID')
            ->get(['ID', 'MobileNumber']);

        $idByPhone = [];
        foreach ($rows as $row) {
            $phone = $this->normalizePhone((string) ($row->MobileNumber ?? ''));
            $id = (int) ($row->ID ?? 0);
            if ($phone === '' || ! isset($candidateLookup[$phone]) || $id <= 0 || isset($idByPhone[$phone])) {
                continue;
            }
            $idByPhone[$phone] = $id;
        }

        foreach ($idByPhone as $phone => $id) {
            foreach ($phoneToCfa[$phone] ?? [] as $cfaId) {
                if (! isset($phase1ByCfa[$cfaId]) || $phase1ByCfa[$cfaId] <= 0) {
                    $phase1ByCfa[$cfaId] = $id;
                }
            }
        }
    }

    /**
     * @param  list<int>  $cfaIds
     * @param  array<int, int>  $legacyAppByCfa
     * @return array<int, list<array{phase: string, label: string, detail: ?string, status: ?string}>>
     */
    private function loadPhase3(array $cfaIds, array $legacyAppByCfa): array
    {
        if ($cfaIds === [] || ! Schema::hasTable('service_cases')) {
            return [];
        }

        $legacyIds = array_values(array_unique(array_filter(array_map('intval', array_values($legacyAppByCfa)), fn (int $id) => $id > 0)));

        $select = ['id', 'cfa_submission_id', 'service_id', 'status', 'payload'];
        if (ServiceCase::supportsLegacyApplicationLink()) {
            $select[] = 'legacy_application_id';
        }
        foreach (['delivered_on', 'approved_at', 'completed_at', 'created_at'] as $dateColumn) {
            if (Schema::hasColumn('service_cases', $dateColumn)) {
                $select[] = $dateColumn;
            }
        }

        $cases = ServiceCase::query()
            ->with(['service:id,name,field_schema'])
            ->where(function ($q) use ($cfaIds, $legacyIds): void {
                if ($cfaIds !== []) {
                    $q->whereIn('cfa_submission_id', $cfaIds);
                }
                if (ServiceCase::supportsLegacyApplicationLink() && $legacyIds !== []) {
                    $method = $cfaIds !== [] ? 'orWhereIn' : 'whereIn';
                    $q->{$method}('legacy_application_id', $legacyIds);
                }
                if ($cfaIds === [] && $legacyIds === []) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->where('status', '!=', ServiceCase::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->get($select);

        $cfaByLegacy = [];
        foreach ($legacyAppByCfa as $cfaId => $legacyId) {
            $cfaByLegacy[(int) $legacyId] = (int) $cfaId;
        }

        $out = [];
        $seen = [];

        foreach ($cases as $case) {
            $cfaId = (int) ($case->cfa_submission_id ?? 0);
            if ($cfaId <= 0 && (int) ($case->legacy_application_id ?? 0) > 0) {
                $cfaId = (int) ($cfaByLegacy[(int) $case->legacy_application_id] ?? 0);
            }
            if ($cfaId <= 0) {
                continue;
            }

            $dedupeKey = $cfaId.':p3:'.$case->id;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $label = trim((string) ($case->service?->name ?? ''));
            if ($label === '') {
                continue;
            }

            $out[$cfaId][] = [
                'phase' => 'phase3',
                'label' => $label,
                'detail' => null,
                'status' => (string) $case->status,
                'date' => $this->formatServiceDate(
                    $case->serviceDateForReporting()
                        ?? $case->approved_at
                        ?? $case->completed_at
                        ?? $case->created_at
                        ?? null
                ),
            ];
        }

        return $out;
    }

    /**
     * Dedicated Market Linkage module rows (not catalog service_cases).
     *
     * @param  list<int>  $cfaIds
     * @param  array<int, int>  $legacyAppByCfa
     * @return array<int, list<array{phase: string, label: string, detail: ?string, status: ?string}>>
     */
    private function loadMarketLinkages(array $cfaIds, array $legacyAppByCfa): array
    {
        if ($cfaIds === [] || ! Schema::hasTable('market_linkage_submissions')) {
            return [];
        }

        $legacyIds = array_values(array_unique(array_filter(
            array_map('intval', array_values($legacyAppByCfa)),
            fn (int $id) => $id > 0
        )));

        $query = MarketLinkageSubmission::query()
            ->with(['partners' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->where(function ($q) use ($cfaIds, $legacyIds): void {
                if ($cfaIds !== []) {
                    $q->whereIn('cfa_submission_id', $cfaIds);
                }
                if ($legacyIds !== []) {
                    $method = $cfaIds !== [] ? 'orWhereIn' : 'whereIn';
                    $q->{$method}('legacy_application_id', $legacyIds);
                }
                if ($cfaIds === [] && $legacyIds === []) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('created_at');

        if (MarketLinkageSubmission::supportsWorkflow() && Schema::hasColumn('market_linkage_submissions', 'status')) {
            $query->where('status', '!=', ServiceCase::STATUS_CANCELLED);
        }

        $submissions = $query->get([
            'id',
            'cfa_submission_id',
            'legacy_application_id',
            'status',
        ]);

        $cfaByLegacy = [];
        foreach ($legacyAppByCfa as $cfaId => $legacyId) {
            $cfaByLegacy[(int) $legacyId] = (int) $cfaId;
        }

        $out = [];
        $seen = [];

        foreach ($submissions as $submission) {
            $cfaId = (int) ($submission->cfa_submission_id ?? 0);
            if ($cfaId <= 0 && (int) ($submission->legacy_application_id ?? 0) > 0) {
                $cfaId = (int) ($cfaByLegacy[(int) $submission->legacy_application_id] ?? 0);
            }
            if ($cfaId <= 0) {
                continue;
            }

            $status = MarketLinkageSubmission::supportsWorkflow()
                ? (string) ($submission->status ?? '')
                : ServiceCase::STATUS_APPROVED;
            $status = $status !== '' ? $status : null;

            $partners = $submission->partners;
            if ($partners->isEmpty()) {
                $dedupeKey = $cfaId.':ml:'.$submission->id;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                $out[$cfaId][] = [
                    'phase' => 'phase3',
                    'label' => MarketLinkageSubmission::SERVICE_LIST_LABEL,
                    'detail' => null,
                    'status' => $status,
                ];

                continue;
            }

            foreach ($partners as $partner) {
                $partnerId = (int) ($partner->id ?? 0);
                $dedupeKey = $cfaId.':ml:'.$submission->id.':'.$partnerId;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                $partnerName = trim((string) ($partner->partner_name ?? ''));
                $mode = MarketLinkageSubmission::linkageModeLabel((string) ($partner->linkage_mode ?? ''));
                $mode = $mode !== '' ? $mode : null;

                $labelParts = [MarketLinkageSubmission::SERVICE_LIST_LABEL];
                if ($mode !== null) {
                    $labelParts[] = $mode;
                }
                if ($partnerName !== '') {
                    $labelParts[] = $partnerName;
                }

                $detailParts = array_filter([$mode, $partnerName !== '' ? $partnerName : null]);

                $out[$cfaId][] = [
                    'phase' => 'phase3',
                    'label' => implode(' · ', $labelParts),
                    'detail' => $detailParts !== [] ? implode(' · ', $detailParts) : null,
                    'status' => $status,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $legacyAppIds
     * @return array<int, list<array{phase: string, label: string, detail: ?string, status: ?string}>>
     */
    private function loadPhase2(array $legacyAppIds): array
    {
        if ($legacyAppIds === [] || ! $this->legacyPhase2Available()) {
            return [];
        }

        $phase2Select = ['id', 'application_id', 'category', 'service_name'];
        if (Schema::connection('legacy')->hasColumn('rbi_services_assigned', 'assigned_date')) {
            $phase2Select[] = 'assigned_date';
        }

        $rows = DB::connection('legacy')
            ->table('rbi_services_assigned')
            ->whereIn('application_id', $legacyAppIds)
            ->orderBy('service_name')
            ->get($phase2Select);

        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            $appId = (int) ($row->application_id ?? 0);
            $name = trim((string) ($row->service_name ?? ''));
            if ($appId <= 0 || $name === '') {
                continue;
            }

            $category = trim((string) ($row->category ?? ''));
            // A beneficiary can receive the same service more than once. Keep
            // each source row as a delivery; only suppress an accidental repeat
            // of the same database row in this loader.
            $dedupeKey = $appId.':p2:'.((int) ($row->id ?? 0));
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $out[$appId][] = [
                'phase' => 'phase2',
                'label' => $name,
                'detail' => $category !== '' ? $category : null,
                'status' => null,
                'date' => $this->formatServiceDate($row->assigned_date ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $phase1Ids
     * @return array<int, list<array{phase: string, label: string, detail: ?string, status: ?string}>>
     */
    private function loadPhase1(array $phase1Ids): array
    {
        if ($phase1Ids === [] || ! $this->legacyPhase1Available()) {
            return [];
        }

        $rows = DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->whereIn('ID', $phase1Ids)
            ->get();

        $out = [];
        $applicationNoById = [];
        foreach ($rows as $row) {
            $id = (int) ($row->ID ?? 0);
            if ($id <= 0) {
                continue;
            }
            $applicationNoById[$id] = trim((string) ($row->ApplicationNumber ?? ''));
            $out[$id] = $this->extractPhase1Services((array) $row);
        }

        $childServicesById = $this->loadPhase1ChildServices($applicationNoById);
        $jitServicesById = $this->loadPhase1JitServices($phase1Ids);

        foreach ($phase1Ids as $id) {
            $id = (int) $id;
            $child = $childServicesById[$id] ?? [];

            // `services` is the authoritative Phase 1 delivery table. The wide
            // tblapplication flags pre-date it and are used only when an
            // applicant has no child delivery records at all.
            $out[$id] = array_merge(
                $child !== [] ? $child : ($out[$id] ?? []),
                $jitServicesById[$id] ?? [],
            );
        }

        return $out;
    }

    /**
     * @param  array<int,string>  $applicationNoById
     * @return array<int,list<array{phase:string,label:string,detail:?string,status:?string,date:?string}>>
     */
    private function loadPhase1ChildServices(array $applicationNoById): array
    {
        if ($applicationNoById === [] || ! Schema::connection('legacy_phase1')->hasTable('services')) {
            return [];
        }

        $idByApplicationNo = [];
        foreach ($applicationNoById as $id => $applicationNo) {
            if ($applicationNo !== '') {
                $idByApplicationNo[$applicationNo] = (int) $id;
            }
        }

        $out = [];
        foreach (array_chunk(array_keys($idByApplicationNo), 500) as $applicationNumbers) {
            $rows = DB::connection('legacy_phase1')
                ->table('services')
                ->whereIn('ApplicationNumber', $applicationNumbers)
                ->orderBy('id')
                ->get(['id', 'ApplicationNumber', 'servicename', 'description', 'other', 'service_date']);

            foreach ($rows as $row) {
                $id = (int) ($idByApplicationNo[trim((string) ($row->ApplicationNumber ?? ''))] ?? 0);
                $label = trim((string) ($row->description ?? ''));
                if ($id <= 0 || $this->isPlaceholderService($label)) {
                    continue;
                }

                $detailParts = array_values(array_filter([
                    trim((string) ($row->other ?? '')),
                    trim((string) ($row->servicename ?? '')),
                ], fn (string $value): bool => $value !== '' && ! $this->isPlaceholderService($value)));

                $out[$id][] = [
                    'phase' => 'phase1',
                    'label' => $label,
                    'detail' => $detailParts !== [] ? implode(' | ', $detailParts) : null,
                    'status' => null,
                    'date' => $this->formatServiceDate($row->service_date ?? null),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $phase1Ids
     * @return array<int,list<array{phase:string,label:string,detail:?string,status:?string,date:?string}>>
     */
    private function loadPhase1JitServices(array $phase1Ids): array
    {
        if ($phase1Ids === [] || ! Schema::connection('legacy_phase1')->hasTable('rbi_services_assigned_jit')) {
            return [];
        }

        $out = [];
        foreach (array_chunk($phase1Ids, 500) as $idChunk) {
            $rows = DB::connection('legacy_phase1')
                ->table('rbi_services_assigned_jit')
                ->whereIn('legacy_app_id', $idChunk)
                ->orderBy('id')
                ->get(['id', 'legacy_app_id', 'service_name', 'category', 'assigned_date']);

            foreach ($rows as $row) {
                $id = (int) ($row->legacy_app_id ?? 0);
                $label = trim((string) ($row->service_name ?? ''));
                if ($id <= 0 || $this->isPlaceholderService($label)) {
                    continue;
                }

                $out[$id][] = [
                    'phase' => 'phase1',
                    'label' => $label,
                    'detail' => trim((string) ($row->category ?? '')) ?: null,
                    'status' => null,
                    'date' => $this->formatServiceDate($row->assigned_date ?? null),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{phase: string, label: string, detail: ?string, status: ?string}>
     */
    private function extractPhase1Services(array $row): array
    {
        $services = [];

        /** @var list<array{column: string, label: string, type: string, detail?: string}> $fields */
        $fields = config('legacy_phase1.service_fields', []);

        foreach ($fields as $field) {
            $column = (string) ($field['column'] ?? '');
            $label = (string) ($field['label'] ?? $column);
            $type = (string) ($field['type'] ?? 'yes');
            $value = $row[$column] ?? null;

            if ($type === 'yes') {
                if (! $this->isYes($value)) {
                    continue;
                }
                $detail = null;
                $detailCol = (string) ($field['detail'] ?? '');
                if ($detailCol !== '' && trim((string) ($row[$detailCol] ?? '')) !== '') {
                    $detail = trim((string) $row[$detailCol]);
                }
                $services[] = ['phase' => 'phase1', 'label' => $label, 'detail' => $detail, 'status' => null];
            } elseif ($type === 'text') {
                $text = trim((string) ($value ?? ''));
                if ($text === '' || $this->isNo($text)) {
                    continue;
                }
                $services[] = ['phase' => 'phase1', 'label' => $label, 'detail' => $text, 'status' => null];
            }
        }

        $partners = [];
        foreach (['partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'] as $col) {
            $raw = trim((string) ($row[$col] ?? ''));
            if ($raw === '' || $this->isNo($raw)) {
                continue;
            }
            foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && ! $this->isNo($part) && stripos($part, 'offline') === false && stripos($part, 'no,') !== 0) {
                    $partners[] = $part;
                }
            }
        }
        $partners = array_values(array_unique($partners));
        if ($partners !== []) {
            $services[] = [
                'phase' => 'phase1',
                'label' => 'Market linkage',
                'detail' => implode(', ', array_slice($partners, 0, 3)).(count($partners) > 3 ? '…' : ''),
                'status' => null,
            ];
        }

        return $services;
    }

    private function isYes(mixed $value): bool
    {
        $v = mb_strtolower(trim((string) ($value ?? '')));

        return in_array($v, ['yes', 'y', '1', 'true'], true);
    }

    private function isNo(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a'], true);
    }

    private function isPlaceholderService(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['', '0', '#n/a', 'n/a', 'na', '-'], true);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) >= 10 ? substr($digits, -10) : '';
    }

    private function legacyPhase2Available(): bool
    {
        if ((string) config('database.connections.legacy.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy')->hasTable('rbi_services_assigned');
        } catch (\Throwable) {
            return false;
        }
    }

    private function legacyPhase1Available(): bool
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatServiceDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value)->format('d M Y');
        }

        $raw = trim((string) $value);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', substr($raw, 0, 10))->format('d M Y');
            }
            if (str_contains($raw, '/')) {
                return \Carbon\Carbon::createFromFormat('n/j/Y', $raw)->format('d M Y');
            }
            if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $raw) === 1) {
                return \Carbon\Carbon::createFromFormat('j-n-Y', $raw)->format('d M Y');
            }

            return \Carbon\Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
