<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Services\CfaSubmissionAuditSnapshot;
use Carbon\Carbon;
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

        $submissions = $this->filteredQuery($filters)
            ->with(['district', 'referralUser', 'fiscalYear'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cfa.index', [
            'submissions' => $submissions,
            'districts' => $districts,
            'sectors' => config('cfa.business_categories'),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $query = $this->filteredQuery($filters)
            ->with(['district:id,name', 'referralUser:id,name', 'fiscalYear:id,code,name']);

        $payloadColumnsMap = $this->discoverPayloadColumns((clone $query)->reorder());
        $payloadHeaders = array_keys($payloadColumnsMap);

        $baseHeaders = [
            'application_no',
            'submitted_at_ist',
            'applicant_name',
            'phone',
            'district',
            'lgd_state_code',
            'lgd_district_code',
            'lgd_block_code',
            'source',
            'referral_staff',
            'fiscal_year',
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
                        $row->lgd_state_code ?? '',
                        $row->lgd_district_code ?? '',
                        $row->lgd_block_code ?? '',
                        $row->source ?? '',
                        $row->referralUser?->name ?? '',
                        $row->fiscalYear?->code ?? $row->fiscalYear?->name ?? '',
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
     * @return array{name: string, district_id: int|null, sector: string, from: string, to: string}
     */
    private function extractFilters(Request $request): array
    {
        $name = trim((string) $request->query('name', ''));
        $districtId = $request->query('district_id');
        $sector = trim((string) $request->query('sector', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

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
            'district_id' => $districtId ? (int) $districtId : null,
            'sector' => $sector,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Same CFA scope as the state dashboard hero counts (see StateAdminDashboardService $phase3Scope).
     */
    private function applyPhase3DashboardScope(Builder $query): Builder
    {
        $phase3FloorDate = Carbon::create(2026, 4, 1)->startOfDay();
        $activeFyId = (int) (optional(FiscalYear::phase3Default())->id ?? 0);

        return $query->when(
            $activeFyId > 0,
            fn ($q) => $q->where('fiscal_year_id', $activeFyId),
            fn ($q) => $q->where('created_at', '>=', $phase3FloorDate)
        );
    }

    /**
     * @param  array{name: string, district_id: int|null, sector: string, from: string, to: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return $this->applyPhase3DashboardScope(CfaSubmission::query())
            ->when($filters['name'] !== '', fn ($q) => $q->where('applicant_name', 'like', '%'.$filters['name'].'%'))
            ->when($filters['district_id'], fn ($q) => $q->where('district_id', $filters['district_id']))
            ->when($filters['sector'] !== '', fn ($q) => $q->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.business_category')) = ?",
                [$filters['sector']]
            ))
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']));
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
