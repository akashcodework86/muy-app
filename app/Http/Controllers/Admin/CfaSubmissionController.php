<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Services\Cfa\CfaFyOnboardingStatsService;
use App\Services\Cfa\CfaSubmissionListQuery;
use App\Services\CfaSubmissionAuditSnapshot;
use App\Services\LegacyPhase1ApplicationDetailService;
use App\Services\LegacyPhase2ApplicationDetailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CfaSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $districts = District::orderBy('name')->get(['id', 'name']);
        $filters = $this->extractFilters($request);
        $scopeCounts = CfaSubmissionListQuery::scopeCounts($filters);
        $fyOnboarding = CfaFyOnboardingStatsService::breakdown(
            ! empty($filters['district_id']) ? (int) $filters['district_id'] : null
        );

        $submissions = CfaSubmissionListQuery::applyFilters(CfaSubmission::query(), $filters)
            ->with(['district', 'referralUser.designationRecord', 'fiscalYear', 'onboardingBatchMembership'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CfaSubmission $row) => CfaSubmissionListQuery::enrichSubmission($row));

        return view('admin.cfa.index', [
            'submissions' => $submissions,
            'districts' => $districts,
            'blocks' => $this->blocksForFilter($filters['district_id'] ?? null),
            'sectors' => config('cfa.business_categories'),
            'designations' => Designation::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'scopeCounts' => $scopeCounts,
            'fyOnboarding' => $fyOnboarding,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $query = CfaSubmissionListQuery::applyFilters(CfaSubmission::query(), $filters)
            ->with(['district:id,name', 'referralUser:id,name,designation_id', 'referralUser.designationRecord:id,name', 'fiscalYear:id,code,name', 'onboardingBatchMembership']);

        $payloadColumnsMap = $this->discoverPayloadColumns((clone $query)->reorder());
        $payloadHeaders = array_keys($payloadColumnsMap);

        $baseHeaders = [
            'application_no',
            'submitted_at_ist',
            'applicant_name',
            'phone',
            'district',
            'block',
            'lgd_state_code',
            'lgd_district_code',
            'lgd_block_code',
            'source',
            'referral_staff',
            'referral_designation',
            'fiscal_year',
            'onboard_status',
        ];
        $headers = array_merge($baseHeaders, $payloadHeaders);

        $filename = 'cfa-applications-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $headers, $payloadColumnsMap): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            (clone $query)->reorder()->chunkById(500, function ($rows) use ($out, $payloadColumnsMap): void {
                foreach ($rows as $row) {
                    $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
                    $phone = (string) ($row->phone ?? '');
                    if ($phone !== '' && preg_match('/^[\d\s+\-]{10,}$/', $phone)) {
                        $phone = "\t".$phone;
                    }
                    $record = [
                        $row->application_no ?? '',
                        optional($row->created_at)->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') ?? '',
                        $row->applicant_name ?? '',
                        $phone,
                        $row->district?->name ?? '',
                        $this->blockFromPayload($row),
                        $row->lgd_state_code ?? '',
                        $row->lgd_district_code ?? '',
                        $row->lgd_block_code ?? '',
                        $row->source ?? '',
                        $row->referralUser?->name ?? '',
                        $row->referralUser?->designationRecord?->name ?? '',
                        $row->fiscalYear?->code ?? $row->fiscalYear?->name ?? '',
                        $row->onboardingBatchMembership !== null ? 'Onboarded' : 'Non onboarded',
                    ];
                    foreach ($payloadColumnsMap as $originalPayloadKey) {
                        $record[] = $this->toCsvValue($payload[$originalPayloadKey] ?? null);
                    }
                    fputcsv($out, $record);
                }
            }, 'id');
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function toCsvValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    /**
     * Union of all top-level JSON keys in `payload` across the filtered export scope.
     *
     * @return array<string, string> header column => original payload key
     */
    private function discoverPayloadColumns(Builder $query): array
    {
        $keys = [];

        $query->select(['id', 'payload'])->chunkById(1000, function ($rows) use (&$keys): void {
            foreach ($rows as $row) {
                if (! is_array($row->payload)) {
                    continue;
                }
                foreach (array_keys($row->payload) as $key) {
                    $key = trim((string) $key);
                    if ($key === '') {
                        continue;
                    }
                    $safe = preg_replace('/[^a-zA-Z0-9_]+/', '_', $key) ?: 'field';
                    $column = 'payload_'.$safe;
                    $suffix = 2;
                    while (isset($keys[$column]) && $keys[$column] !== $key) {
                        $column = 'payload_'.$safe.'_'.$suffix;
                        $suffix++;
                    }
                    $keys[$column] = $key;
                }
            }
        }, 'id');

        ksort($keys);

        return $keys;
    }

    /**
     * @return array{name: string, application_no: string, district_id: int|null, block: string, sector: string, caste: string, designation_id: int|null, from: string, to: string, onboard: string}
     */
    private function extractFilters(Request $request): array
    {
        $name = trim((string) $request->query('name', ''));
        $applicationNo = trim((string) $request->query('application_no', ''));
        $districtId = $request->query('district_id');
        $block = trim((string) $request->query('block', ''));
        $sector = trim((string) $request->query('sector', ''));
        $caste = CfaSubmissionListQuery::normalizeCasteParam($request);
        $designationId = $request->query('designation_id');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $onboard = CfaSubmissionListQuery::normalizeOnboardParam($request);

        $v = Validator::make(
            ['from' => $from, 'to' => $to],
            [
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d'],
            ]
        );
        if ($v->fails()) {
            $from = '';
            $to = '';
        }

        return [
            'name' => $name,
            'application_no' => $applicationNo,
            'district_id' => $districtId ? (int) $districtId : null,
            'block' => $block,
            'sector' => $sector,
            'caste' => $caste,
            'designation_id' => $designationId ? (int) $designationId : null,
            'from' => $from,
            'to' => $to,
            'onboard' => $onboard,
        ];
    }

    /**
     * @return list<string>
     */
    private function blocksForFilter(?int $districtId): array
    {
        if ($districtId) {
            $blocks = DistrictBlock::orderedNamesForDistrict($districtId);
            if ($blocks !== []) {
                return $blocks;
            }

            $district = District::query()->find($districtId);
            if ($district) {
                return config('cfa.blocks_by_district.'.$district->name, []);
            }

            return [];
        }

        return DistrictBlock::query()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    private function blockFromPayload(CfaSubmission $row): string
    {
        $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
        $block = trim((string) ($payload['block'] ?? ''));

        return $block !== '' ? $block : '';
    }

    public function show(CfaSubmission $cfa_submission): View
    {
        $cfa_submission->load(['district', 'referralUser', 'fiscalYear']);

        $cfaEditLogs = AuditLog::query()
            ->where('subject_type', CfaSubmission::class)
            ->where('subject_id', $cfa_submission->id)
            ->where('action', CfaSubmissionAuditSnapshot::ACTION_UPDATED)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $phase1Detail = app(LegacyPhase1ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($phase1Detail) && isset($phase1Detail['viewRow'])) {
            return view('admin.cfa.phase1-legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $phase1Detail,
                'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                    'fiscal_year_id' => $cfa_submission->fiscal_year_id,
                ])),
            ]);
        }

        $legacyDetail = app(LegacyPhase2ApplicationDetailService::class)->tryBuild($cfa_submission);
        if (is_array($legacyDetail) && isset($legacyDetail['viewRow'])) {
            return view('admin.cfa.legacy-detail', [
                'submission' => $cfa_submission,
                'legacyDetail' => $legacyDetail,
                'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                    'fiscal_year_id' => $cfa_submission->fiscal_year_id,
                ])),
            ]);
        }

        return view('admin.cfa.show', [
            'submission' => $cfa_submission,
            'cfaIndexUrl' => route('admin.cfa.index', array_filter([
                'fiscal_year_id' => $cfa_submission->fiscal_year_id,
            ])),
            'cfaEditUrl' => null,
            'cfaEditLogs' => $cfaEditLogs,
        ]);
    }
}
