<?php

namespace App\Services\Exports;

use App\Models\District;
use App\Models\FiscalYear;
use App\Models\MarketLinkageSubmission;
use App\Models\ServiceCase;
use App\Services\BatchMemberInterventionsSummaryService;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\ReapIncubateeTargetProgressService;
use App\Support\ConvergenceReapSupport;
use App\Support\ConvergenceReapSupportDeliverablesSupport;
use App\Support\PotentialLakhpatiOnboardingSql;
use App\Support\ReapIncubateeTargets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SHG-member / CBO cohort packs: summary service counts + member detail with services.
 */
final class Phase3ShgCboReapPackDataService
{
    /** MIS 4.2 leaf codes (program_deliverables) => display label */
    private const LEGAL_4_2_INDICATORS = [
        'artisan_card' => '4.2.1 Artisan Card',
        'fssai' => '4.2.2 FSSAI',
        'utdb_registration' => '4.2.3 UTDB',
        'gst' => '4.2.4 GST Registration',
        'trademark' => '4.2.5 Trademark application filling',
        'gi_seller' => '4.2.6 GI Seller Registration',
        'advance_licensing_support' => '4.2.7 Advance Licensing Support',
    ];

    public function __construct(
        private readonly BatchMemberInterventionsSummaryService $interventions,
        private readonly LegacyApplicationServiceCaseSupport $legacyServiceCases,
        private readonly ReapIncubateeTargetProgressService $reapProgress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->endOfDay();
        $periodFrom = Carbon::parse((string) config('program_deliverables.phase3_floor_date', '2026-04-01'))->startOfDay();
        $periodTo = $asOf->copy()->endOfDay();
        $fiscalYear = FiscalYear::query()->where('code', ReapIncubateeTargets::configuredFiscalYearCode())->first()
            ?? FiscalYear::phase3Default();

        $shg = $this->buildCohortPack(
            label: 'SHG members',
            qualifySql: PotentialLakhpatiOnboardingSql::phase3ShgMembersOnboardingSql(),
            fiscalYear: $fiscalYear instanceof FiscalYear ? $fiscalYear : null,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
        );

        $cbo = $this->buildCohortPack(
            label: 'CBO',
            qualifySql: PotentialLakhpatiOnboardingSql::phase3CboOnboardingSql(),
            fiscalYear: $fiscalYear instanceof FiscalYear ? $fiscalYear : null,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
        );

        $reap82 = $this->buildReap82Pack(
            $fiscalYear instanceof FiscalYear ? $fiscalYear : null,
            $periodFrom,
            $periodTo,
        );

        return [
            'meta' => [
                'title' => 'Phase 3 SHG members & CBO — CFA / onboard / services (+ separate 8.2 REAP)',
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'as_of' => $asOf->timezone(config('app.timezone'))->format('d M Y, g:i A T'),
                'fiscal_year' => (string) ($fiscalYear?->code ?? '2026-27'),
                'rules' => [
                    'SHG members = Phase 3 Individual with Member of SHG/CBO = Yes',
                    'CBO = Phase 3 category CBO',
                    'Status: approved / locked only for service & market-linkage counts',
                    'Period: onboarding_date / service achievement 1 Apr 2026 → as-of',
                    '4.2 Legal & Licensing from approved service cases + approved acceleration items',
                    '8.2 is on separate sheets (statewide, not filtered to SHG/CBO) — same scope as deliverables',
                ],
            ],
            'shg' => $shg,
            'cbo' => $cbo,
            'reap82' => $reap82,
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     summary_rows: list<array{section: string, metric: string, count: int|string}>,
     *     details: list<array<string, mixed>>
     * }
     */
    private function buildCohortPack(
        string $label,
        string $qualifySql,
        ?FiscalYear $fiscalYear,
        Carbon $periodFrom,
        Carbon $periodTo,
    ): array {
        $totalCfa = $this->cfaCount($qualifySql, $fiscalYear, $periodFrom, $periodTo);
        $onboarded = $this->onboardedMembers($qualifySql, $periodFrom, $periodTo);
        $onboardedCount = count($onboarded);

        $membersForServices = array_map(static fn (array $m): array => [
            'id' => (int) $m['cfa_id'],
            'application_no' => (string) $m['application_no'],
            'phone' => (string) ($m['phone'] ?? ''),
            'legacy_application_id' => (int) ($m['legacy_application_id'] ?? 0),
            'legacy_phase1_id' => 0,
        ], $onboarded);

        $withServices = $this->interventions->attachServices($membersForServices);
        $servicesByCfa = [];
        foreach ($withServices as $row) {
            $servicesByCfa[(int) $row['id']] = $row['services'] ?? [];
        }

        $cfaIds = array_values(array_map(static fn (array $m): int => (int) $m['cfa_id'], $onboarded));
        $appNos = array_values(array_filter(array_map(
            static fn (array $m): string => trim((string) $m['application_no']),
            $onboarded
        )));

        $serviceCaseCounts = $this->approvedServiceCaseCountsByName($cfaIds, $periodFrom, $periodTo);
        $market = $this->marketLinkageStatsForCohort($cfaIds, $appNos, $periodFrom, $periodTo);
        $legal42 = $this->legalLicensing42FromServiceCases($cfaIds, $periodFrom, $periodTo);
        $crossChecks = $this->buildServiceCrossChecks($serviceCaseCounts, $legal42);

        $summary = [];
        $summary[] = ['section' => 'Cohort', 'metric' => 'Total CFA (Phase 3, this group)', 'count' => $totalCfa];
        $summary[] = ['section' => 'Cohort', 'metric' => 'Total onboarded (locked batches)', 'count' => $onboardedCount];

        $summary[] = ['section' => '6.3 Market linkage', 'metric' => 'Incubatees linked — Offline', 'count' => $market['offline_incubatees']];
        $summary[] = ['section' => '6.3 Market linkage', 'metric' => 'Incubatees linked — Online', 'count' => $market['online_incubatees']];
        $summary[] = ['section' => '6.3 Market linkage', 'metric' => 'Incubatees linked — Total unique', 'count' => $market['total_incubatees']];
        $summary[] = ['section' => '6.3 Market linkage', 'metric' => 'Partner linkages (count)', 'count' => $market['partner_linkages']];
        $summary[] = ['section' => '6.3 Market linkage', 'metric' => 'Unique partner names', 'count' => $market['unique_partners']];

        $summary[] = ['section' => '4.2 Legal & Licensing Support', 'metric' => '4.2 Total (service cases mapped to 4.2)', 'count' => (int) ($legal42['total'] ?? 0)];
        foreach (self::LEGAL_4_2_INDICATORS as $misCode => $metricLabel) {
            $summary[] = [
                'section' => '4.2 Legal & Licensing Support',
                'metric' => $metricLabel,
                'count' => (int) ($legal42['counts'][$misCode] ?? 0),
            ];
        }

        $summary[] = ['section' => 'All services (approved/completed cases)', 'metric' => 'Total service cases', 'count' => array_sum($serviceCaseCounts)];
        if ($serviceCaseCounts === []) {
            $summary[] = ['section' => 'All services (approved/completed cases)', 'metric' => '(no approved service cases for onboarded cohort)', 'count' => 0];
        } else {
            foreach ($serviceCaseCounts as $name => $count) {
                $summary[] = [
                    'section' => 'All services (approved/completed cases)',
                    'metric' => $name,
                    'count' => $count,
                ];
            }
        }

        foreach ($crossChecks as $check) {
            $summary[] = [
                'section' => 'Cross-check (4.2 vs All services)',
                'metric' => $check['metric'],
                'count' => $check['count'],
            ];
        }

        $details = [];
        $sn = 0;
        foreach ($onboarded as $member) {
            $sn++;
            $cfaId = (int) $member['cfa_id'];
            $services = $servicesByCfa[$cfaId] ?? [];
            $serviceLabels = [];
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
                $serviceLabels[] = $bit;
            }

            $ml = $market['by_cfa'][$cfaId] ?? ['modes' => [], 'partners' => [], 'partner_count' => 0];
            $legalLabels = $legal42['by_cfa'][$cfaId] ?? [];

            $details[] = [
                'sn' => $sn,
                'cfa_id' => $cfaId,
                'application_no' => $member['application_no'],
                'applicant' => $member['applicant'],
                'category' => $member['category'],
                'is_member' => $member['is_member'],
                'shg_cbo_name' => $member['shg_cbo_name'],
                'phone' => $member['phone'],
                'sector' => $member['sector'],
                'district' => $member['district'],
                'hub' => $member['hub'],
                'batch' => $member['batch'],
                'onboarding_date' => $member['onboarding_date'],
                'services_count' => count($serviceLabels),
                'services' => $serviceLabels !== [] ? implode(' | ', $serviceLabels) : '—',
                'market_linkage_mode' => $ml['modes'] !== [] ? implode(', ', $ml['modes']) : '—',
                'market_partners' => $ml['partners'] !== [] ? implode(', ', $ml['partners']) : '—',
                'market_partner_count' => (int) $ml['partner_count'],
                'legal_4_2' => $legalLabels !== [] ? implode(' | ', $legalLabels) : '—',
            ];
        }

        return [
            'label' => $label,
            'summary_rows' => $summary,
            'details' => $details,
        ];
    }

    private function cfaCount(string $qualifySql, ?FiscalYear $fiscalYear, Carbon $periodFrom, Carbon $periodTo): int
    {
        if (! Schema::hasTable('cfa_submissions')) {
            return 0;
        }

        $query = DB::table('cfa_submissions as cs')->whereRaw($qualifySql);
        $fyId = (int) ($fiscalYear?->id ?? 0);
        if ($fyId > 0 && Schema::hasColumn('cfa_submissions', 'fiscal_year_id')) {
            $query->where('cs.fiscal_year_id', $fyId);
        } else {
            $query->whereBetween('cs.created_at', [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()]);
        }

        return (int) $query->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function onboardedMembers(string $qualifySql, Carbon $periodFrom, Carbon $periodTo): array
    {
        if (! Schema::hasTable('onboarding_batch_cfa') || ! Schema::hasTable('onboarding_batches')) {
            return [];
        }

        $categoryJson = PotentialLakhpatiOnboardingSql::payloadJson('$.category');
        $memberJson = PotentialLakhpatiOnboardingSql::payloadJson('$.is_member');
        $shgNameJson = PotentialLakhpatiOnboardingSql::payloadJson('$.shg_name');
        $shgCboNameJson = PotentialLakhpatiOnboardingSql::payloadJson('$.shg_cbo_name');
        $phoneJson = PotentialLakhpatiOnboardingSql::payloadJson('$.phone');
        $sectorJson = PotentialLakhpatiOnboardingSql::payloadJson('$.sector');
        $legacyJson = PotentialLakhpatiOnboardingSql::payloadJson('$.legacy_application_id');

        $rows = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('ob.onboarding_date', '>=', $periodFrom->toDateString())
            ->where('ob.onboarding_date', '<=', $periodTo->toDateString())
            ->whereRaw($qualifySql)
            ->orderBy('d.name')
            ->orderBy('cs.applicant_name')
            ->get([
                'cs.id',
                'cs.application_no',
                'cs.applicant_name',
                'cs.phone as cfa_phone',
                'd.name as district_name',
                'h.name as hub_name',
                'ob.name as batch_name',
                'ob.onboarding_date',
                DB::raw("{$categoryJson} as category"),
                DB::raw("{$memberJson} as is_member"),
                DB::raw("{$shgNameJson} as shg_name"),
                DB::raw("{$shgCboNameJson} as shg_cbo_name"),
                DB::raw("{$phoneJson} as payload_phone"),
                DB::raw("{$sectorJson} as sector"),
                DB::raw("{$legacyJson} as legacy_application_id"),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'cfa_id' => (int) $row->id,
                'application_no' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'category' => (string) ($row->category ?: '—'),
                'is_member' => (string) ($row->is_member ?: '—'),
                'shg_cbo_name' => (string) (($row->shg_cbo_name ?: $row->shg_name) ?: '—'),
                'phone' => (string) (($row->cfa_phone ?: $row->payload_phone) ?: ''),
                'sector' => (string) ($row->sector ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'batch' => (string) ($row->batch_name ?: '—'),
                'onboarding_date' => $row->onboarding_date
                    ? Carbon::parse($row->onboarding_date)->format('d M Y')
                    : '—',
                'legacy_application_id' => (int) ($row->legacy_application_id ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $cfaIds
     * @return array<string, int>
     */
    private function approvedServiceCaseCountsByName(array $cfaIds, Carbon $periodFrom, Carbon $periodTo): array
    {
        if ($cfaIds === [] || ! Schema::hasTable('service_cases')) {
            return [];
        }

        $dateExpr = $this->serviceCaseDateExpression();
        $rows = DB::table('service_cases as sc')
            ->leftJoin('services as s', 's.id', '=', 'sc.service_id')
            ->whereIn('sc.cfa_submission_id', $cfaIds)
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED])
            ->whereBetween(DB::raw($dateExpr), [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()])
            ->groupBy('s.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get([
                DB::raw("COALESCE(NULLIF(TRIM(s.name), ''), '(unnamed service)') as service_name"),
                DB::raw('COUNT(*) as total'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->service_name] = (int) $row->total;
        }

        return $out;
    }

    /**
     * @param  list<int>  $cfaIds
     * @param  list<string>  $appNos
     * @return array{
     *     offline_incubatees: int,
     *     online_incubatees: int,
     *     total_incubatees: int,
     *     partner_linkages: int,
     *     unique_partners: int,
     *     by_cfa: array<int, array{modes: list<string>, partners: list<string>, partner_count: int}>
     * }
     */
    private function marketLinkageStatsForCohort(array $cfaIds, array $appNos, Carbon $periodFrom, Carbon $periodTo): array
    {
        $empty = [
            'offline_incubatees' => 0,
            'online_incubatees' => 0,
            'total_incubatees' => 0,
            'partner_linkages' => 0,
            'unique_partners' => 0,
            'by_cfa' => [],
        ];

        if ($cfaIds === [] || ! Schema::hasTable('market_linkage_submissions') || ! Schema::hasTable('market_linkage_partners')) {
            return $empty;
        }

        $query = DB::table('market_linkage_submissions as mls')
            ->join('market_linkage_partners as mlp', 'mlp.market_linkage_submission_id', '=', 'mls.id')
            ->where(function ($q) use ($cfaIds, $appNos): void {
                $q->whereIn('mls.cfa_submission_id', $cfaIds);
                if ($appNos !== []) {
                    $q->orWhereIn('mls.application_no', $appNos);
                }
            });

        if (MarketLinkageSubmission::supportsWorkflow() && Schema::hasColumn('market_linkage_submissions', 'status')) {
            $query->where('mls.status', ServiceCase::STATUS_APPROVED);
        }

        $query->whereBetween('mlp.linkage_date', [$periodFrom->toDateString(), $periodTo->toDateString()]);

        $rows = $query->get([
            'mls.cfa_submission_id',
            'mls.application_no',
            'mlp.partner_name',
            'mlp.linkage_mode',
        ]);

        $appMap = [];
        if ($cfaIds !== [] && $appNos !== []) {
            $appMap = DB::table('cfa_submissions')
                ->whereIn('id', $cfaIds)
                ->pluck('id', 'application_no')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $cfaIdSet = array_fill_keys($cfaIds, true);
        $byCfa = [];
        $offlineKeys = [];
        $onlineKeys = [];
        $partnerNames = [];
        $partnerLinkages = 0;

        foreach ($rows as $row) {
            $cfaId = (int) ($row->cfa_submission_id ?? 0);
            if ($cfaId <= 0 || ! isset($cfaIdSet[$cfaId])) {
                $appNo = trim((string) ($row->application_no ?? ''));
                $cfaId = (int) ($appMap[$appNo] ?? 0);
            }
            if ($cfaId <= 0 || ! isset($cfaIdSet[$cfaId])) {
                continue;
            }

            $partnerLinkages++;
            $partner = trim((string) ($row->partner_name ?? ''));
            if ($partner !== '') {
                $partnerNames[$partner] = true;
            }

            $mode = MarketLinkageSubmission::linkageModeLabel((string) ($row->linkage_mode ?? ''));
            if (! isset($byCfa[$cfaId])) {
                $byCfa[$cfaId] = ['modes' => [], 'partners' => [], 'partner_count' => 0];
            }
            if ($mode !== '' && $mode !== '—' && ! in_array($mode, $byCfa[$cfaId]['modes'], true)) {
                $byCfa[$cfaId]['modes'][] = $mode;
            }
            if ($partner !== '' && ! in_array($partner, $byCfa[$cfaId]['partners'], true)) {
                $byCfa[$cfaId]['partners'][] = $partner;
            }
            $byCfa[$cfaId]['partner_count']++;

            $key = (string) $cfaId;
            if (strcasecmp($mode, 'Offline') === 0) {
                $offlineKeys[$key] = true;
            }
            if (strcasecmp($mode, 'Online') === 0) {
                $onlineKeys[$key] = true;
            }
        }

        $allKeys = $offlineKeys + $onlineKeys;

        return [
            'offline_incubatees' => count($offlineKeys),
            'online_incubatees' => count($onlineKeys),
            'total_incubatees' => count($allKeys),
            'partner_linkages' => $partnerLinkages,
            'unique_partners' => count($partnerNames),
            'by_cfa' => $byCfa,
        ];
    }

    /**
     * 4.2 counts from approved service cases only (same source as deliverables),
     * mapped via service code / deliverable code / aliases / name keywords.
     *
     * @param  list<int>  $cfaIds
     * @return array{
     *     total: int,
     *     counts: array<string, int>,
     *     by_service_name: array<string, array<string, int>>,
     *     by_cfa: array<int, list<string>>
     * }
     */
    private function legalLicensing42FromServiceCases(array $cfaIds, Carbon $periodFrom, Carbon $periodTo): array
    {
        $counts = [];
        foreach (array_keys(self::LEGAL_4_2_INDICATORS) as $code) {
            $counts[$code] = 0;
        }
        $byServiceName = [];
        $byCfa = [];
        $total = 0;

        if ($cfaIds === [] || ! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return [
                'total' => 0,
                'counts' => $counts,
                'by_service_name' => [],
                'by_cfa' => [],
            ];
        }

        $dateExpr = $this->serviceCaseDateExpression();
        $rows = DB::table('service_cases as sc')
            ->join('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('deliverables as d', 'd.id', '=', 's.deliverable_id')
            ->whereIn('sc.cfa_submission_id', $cfaIds)
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED])
            ->whereBetween(DB::raw($dateExpr), [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()])
            ->get([
                'sc.cfa_submission_id',
                's.code as service_code',
                's.name as service_name',
                'd.code as deliverable_code',
                'd.name as deliverable_name',
                'd.mis_entry_label as deliverable_label',
            ]);

        foreach ($rows as $row) {
            $misCode = $this->resolveMis42Code(
                (string) ($row->service_code ?? ''),
                (string) ($row->deliverable_code ?? ''),
                (string) ($row->service_name ?? ''),
                (string) ($row->deliverable_name ?? ''),
                (string) ($row->deliverable_label ?? ''),
            );
            if ($misCode === null) {
                continue;
            }

            $total++;
            $counts[$misCode]++;
            $serviceName = trim((string) ($row->service_name ?? '')) ?: '(unnamed service)';
            $byServiceName[$misCode][$serviceName] = ($byServiceName[$misCode][$serviceName] ?? 0) + 1;

            $cfaId = (int) ($row->cfa_submission_id ?? 0);
            if ($cfaId > 0) {
                $byCfa[$cfaId][] = self::LEGAL_4_2_INDICATORS[$misCode].' ← '.$serviceName;
            }
        }

        foreach ($byCfa as $cfaId => $labels) {
            $byCfa[$cfaId] = array_values(array_unique($labels));
        }

        return [
            'total' => $total,
            'counts' => $counts,
            'by_service_name' => $byServiceName,
            'by_cfa' => $byCfa,
        ];
    }

    /**
     * Map a service case to one MIS 4.2 code (deliverables-compatible aliases/keywords).
     */
    private function resolveMis42Code(
        string $serviceCode,
        string $deliverableCode,
        string $serviceName = '',
        string $deliverableName = '',
        string $deliverableLabel = '',
    ): ?string {
        $serviceCode = strtolower(trim($serviceCode));
        $deliverableCode = strtolower(trim($deliverableCode));
        if (str_starts_with($deliverableCode, 'svc_')) {
            $deliverableCode = substr($deliverableCode, 4);
        }
        if (str_starts_with($deliverableCode, 'cat_')) {
            $deliverableCode = '';
        }

        $compactService = str_replace('_', '', $serviceCode);
        $aliases = config('program_deliverables.target_code_aliases', []);
        $keywords = config('program_deliverables.achievement_deliverable_keywords', []);

        foreach (array_keys(self::LEGAL_4_2_INDICATORS) as $misCode) {
            $misCompact = str_replace('_', '', $misCode);
            if ($serviceCode === $misCode || $deliverableCode === $misCode) {
                return $misCode;
            }
            if ($compactService !== '' && ($compactService === $misCompact || str_contains($compactService, $misCompact))) {
                return $misCode;
            }

            $aliasList = is_array($aliases[$misCode] ?? null) ? $aliases[$misCode] : [];
            foreach ($aliasList as $alias) {
                $alias = strtolower(trim((string) $alias));
                if ($alias === '') {
                    continue;
                }
                $aliasCompact = str_replace('_', '', $alias);
                if ($serviceCode === $alias || $deliverableCode === $alias) {
                    return $misCode;
                }
                if ($compactService !== '' && ($compactService === $aliasCompact || str_contains($compactService, $aliasCompact))) {
                    return $misCode;
                }
            }
        }

        $haystack = strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $serviceName,
            $deliverableName,
            $deliverableLabel,
            str_replace('_', ' ', $serviceCode),
        ]))) ?? ''));

        $ordered = ['advance_licensing_support', 'gi_seller', 'utdb_registration', 'artisan_card', 'trademark', 'fssai', 'gst'];
        foreach ($ordered as $misCode) {
            $words = is_array($keywords[$misCode] ?? null) ? $keywords[$misCode] : [];
            foreach ($words as $word) {
                $word = strtolower(trim((string) $word));
                if ($word !== '' && str_contains($haystack, $word)) {
                    return $misCode;
                }
            }
        }

        return match (true) {
            str_contains($compactService, 'gst') => 'gst',
            str_contains($compactService, 'fssai') || str_contains($compactService, 'fssa') => 'fssai',
            str_contains($compactService, 'utdb') => 'utdb_registration',
            str_contains($compactService, 'artisancard') || (str_contains($compactService, 'artisan') && str_contains($compactService, 'card')) => 'artisan_card',
            str_contains($compactService, 'trademark') => 'trademark',
            str_contains($compactService, 'giseller') => 'gi_seller',
            str_contains($compactService, 'mandilicense')
                || str_contains($compactService, 'seedlicense')
                || str_contains($compactService, 'pancard')
                || str_contains($compactService, 'labtest')
                || str_contains($compactService, 'advancelicen') => 'advance_licensing_support',
            default => null,
        };
    }

    /**
     * @param  array<string, int>  $serviceCaseCounts
     * @param  array{total: int, counts: array<string, int>, by_service_name: array<string, array<string, int>>, by_cfa: array<int, list<string>>}  $legal42
     * @return list<array{metric: string, count: string}>
     */
    private function buildServiceCrossChecks(array $serviceCaseCounts, array $legal42): array
    {
        $checks = [];
        $allMatch = true;

        foreach (self::LEGAL_4_2_INDICATORS as $misCode => $label) {
            $indicatorCount = (int) ($legal42['counts'][$misCode] ?? 0);
            $parts = $legal42['by_service_name'][$misCode] ?? [];
            $sumParts = (int) array_sum($parts);
            $match = $indicatorCount === $sumParts;

            $breakdown = $parts === []
                ? '—'
                : implode('; ', array_map(
                    static fn (string $name, int $n): string => $name.'='.$n,
                    array_keys($parts),
                    array_values($parts),
                ));

            $fromAllServices = 0;
            foreach ($parts as $name => $n) {
                $fromAllServices += (int) ($serviceCaseCounts[$name] ?? 0);
            }
            $vsAll = $indicatorCount === $fromAllServices;
            if (! $match || ! $vsAll) {
                $allMatch = false;
            }

            $checks[] = [
                'metric' => $label.' → '.$breakdown.' | 4.2='.$indicatorCount.' | parts='.$sumParts.' | All-services='.$fromAllServices.' | '.(($match && $vsAll) ? 'OK' : 'MISMATCH'),
                'count' => ($match && $vsAll) ? 'OK' : 'FAIL',
            ];
        }

        $checks[] = [
            'metric' => 'Overall 4.2 vs contributing service names',
            'count' => $allMatch ? 'ALL OK' : 'HAS MISMATCHES',
        ];
        $checks[] = [
            'metric' => 'All-services total cases',
            'count' => (string) array_sum($serviceCaseCounts),
        ];
        $checks[] = [
            'metric' => '4.2 mapped cases (subset of All-services)',
            'count' => (string) (int) ($legal42['total'] ?? 0),
        ];

        return $checks;
    }

    /**
     * Statewide MIS 8.2 — separate sheets (not filtered to SHG/CBO).
     *
     * @return array{label: string, summary_rows: list<array{section: string, metric: string, count: int|string}>, details: list<array<string, mixed>>}
     */
    private function buildReap82Pack(?FiscalYear $fiscalYear, Carbon $periodFrom, Carbon $periodTo): array
    {
        $districtIds = District::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deliverablesTotal = ConvergenceReapSupportDeliverablesSupport::countCases($districtIds, $periodFrom, $periodTo);
        $details = $this->reap82CaseDetails($districtIds, $periodFrom, $periodTo);

        $buckets = ReapIncubateeTargets::emptyBucketCounts();
        $buckets['unbucketed'] = 0;
        $uniqueCfa = [];

        foreach ($details as $row) {
            $bucketKey = (string) ($row['bucket_key'] ?? '');
            if ($bucketKey !== '' && isset($buckets[$bucketKey])) {
                $buckets[$bucketKey]++;
            } else {
                $buckets['unbucketed']++;
            }
            $cfaId = (int) ($row['cfa_id'] ?? 0);
            if ($cfaId > 0) {
                $uniqueCfa[$cfaId] = true;
            }
        }

        $statewide = $this->reapProgress->statewideProgress($fiscalYear);
        $grand = $statewide['grand_totals'] ?? null;

        $summary = [
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => '8.2 Total cases (deliverables scope)', 'count' => count($details)],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Deliverables verify count', 'count' => $deliverablesTotal],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Unique incubatees', 'count' => count($uniqueCfa)],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Farm · 1 Lakh', 'count' => $buckets['farm_1_lakh']],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Farm · 3 Lakh', 'count' => $buckets['farm_3_lakh']],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Non-farm · 1 Lakh', 'count' => $buckets['non_farm_1_lakh']],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Non-farm · 3 Lakh', 'count' => $buckets['non_farm_3_lakh']],
            ['section' => '8.2 Support to MUY Incubatee through REAP', 'metric' => 'Unbucketed (missing sector/amount)', 'count' => $buckets['unbucketed']],
        ];

        if (is_array($grand) && isset($grand['totals'])) {
            $summary[] = ['section' => 'vs official targets (FY buckets)', 'metric' => 'Target total', 'count' => (int) ($grand['totals']['target'] ?? 0)];
            $summary[] = ['section' => 'vs official targets (FY buckets)', 'metric' => 'Approved (bucketed) total', 'count' => (int) ($grand['totals']['approved'] ?? 0)];
            $summary[] = ['section' => 'vs official targets (FY buckets)', 'metric' => 'Achievement %', 'count' => (int) ($grand['totals']['pct'] ?? 0)];
        }

        foreach (($statewide['rows'] ?? []) as $row) {
            $districtName = (string) ($row['district']['name'] ?? '—');
            $hub = (string) ($row['district']['hub_name'] ?? '—');
            $summary[] = [
                'section' => 'District progress (target / approved)',
                'metric' => $districtName.' ('.$hub.')',
                'count' => ((int) ($row['totals']['approved'] ?? 0)).' / '.((int) ($row['totals']['target'] ?? 0)),
            ];
        }

        return [
            'label' => '8.2 Support to MUY Incubatee through REAP',
            'summary_rows' => $summary,
            'details' => $details,
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @return list<array<string, mixed>>
     */
    private function reap82CaseDetails(array $districtIds, Carbon $periodFrom, Carbon $periodTo): array
    {
        if ($districtIds === [] || ! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return [];
        }

        $dateExpr = $this->serviceCaseDateExpression();
        $sectorExpr = $this->payloadExpression(ConvergenceReapSupport::REAP_SECTOR_KEY);
        $amountExpr = $this->payloadExpression(ConvergenceReapSupport::REAP_AMOUNT_KEY);
        $activityExpr = $this->payloadExpression(ConvergenceReapSupport::REAP_ACTIVITY_KEY);

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->leftJoin('services as s', 's.id', '=', 'sc.service_id')
            ->leftJoin('districts as d', 'd.id', '=', 'cs.district_id')
            ->leftJoin('hubs as h', 'h.id', '=', 'd.hub_id')
            ->whereIn('sc.status', [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED]);

        ConvergenceReapSupportDeliverablesSupport::applyAchievementScope($query, 'sc');
        $this->legacyServiceCases->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
        $query->whereBetween(DB::raw($dateExpr), [$periodFrom->toDateTimeString(), $periodTo->toDateTimeString()]);

        $rows = $query->orderByDesc(DB::raw($dateExpr))->get([
            'sc.id',
            'sc.status',
            'sc.approved_at',
            'sc.completed_at',
            'sc.delivered_on',
            'sc.submitted_at',
            'sc.created_at',
            'sc.cfa_submission_id',
            'cs.application_no',
            'cs.applicant_name',
            'd.name as district_name',
            'h.name as hub_name',
            's.name as service_name',
            's.code as service_code',
            DB::raw($sectorExpr.' as reap_sector'),
            DB::raw($amountExpr.' as reap_amount'),
            DB::raw($activityExpr.' as reap_activity'),
        ]);

        $out = [];
        $sn = 0;
        foreach ($rows as $row) {
            $sn++;
            $sector = is_string($row->reap_sector ?? null) ? (string) $row->reap_sector : '';
            $amount = is_string($row->reap_amount ?? null) ? (string) $row->reap_amount : '';
            $bucketKey = ReapIncubateeTargets::bucketFromPayload(
                $sector !== '' ? $sector : null,
                $amount !== '' ? $amount : null,
            );
            $achievedAt = $row->approved_at ?? $row->completed_at ?? $row->delivered_on ?? $row->submitted_at ?? $row->created_at;

            $out[] = [
                'sn' => $sn,
                'case_id' => (int) $row->id,
                'cfa_id' => (int) ($row->cfa_submission_id ?? 0),
                'application_no' => (string) ($row->application_no ?: '—'),
                'applicant' => (string) ($row->applicant_name ?: '—'),
                'district' => (string) ($row->district_name ?: '—'),
                'hub' => (string) ($row->hub_name ?: '—'),
                'service' => (string) ($row->service_name ?: '—'),
                'service_code' => (string) ($row->service_code ?: '—'),
                'sector' => ConvergenceReapSupport::reapSectorLabel($sector !== '' ? $sector : null),
                'amount' => ConvergenceReapSupport::reapAmountLabel($amount !== '' ? $amount : null),
                'bucket_key' => $bucketKey ?? '',
                'bucket' => $bucketKey ? ReapIncubateeTargets::bucketLabel($bucketKey) : 'Unbucketed',
                'activity' => (string) ($row->reap_activity ?: '—'),
                'status' => (string) ($row->status ?: '—'),
                'date' => $achievedAt ? Carbon::parse($achievedAt)->format('d M Y') : '—',
            ];
        }

        return $out;
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

    private function serviceCaseDateExpression(): string
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
}
