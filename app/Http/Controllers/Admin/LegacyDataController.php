<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyServiceAlias;
use App\Services\AdminAuditLogger;
use App\Services\LegacyData\LegacyDataExplorerService;
use App\Services\LegacyData\LegacyServiceNameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LegacyDataController extends Controller
{
    private const GROUPS = [
        'district' => 'District', 'fy' => 'Financial year', 'phase' => 'Phase',
        'service' => 'Service', 'category' => 'Business category', 'stage' => 'Business stage',
        'gender' => 'Gender', 'education' => 'Education', 'type' => 'Beneficiary type',
    ];

    public function __construct(
        private readonly LegacyDataExplorerService $service,
        private readonly LegacyServiceNameNormalizer $serviceNames,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
        $filters = $this->filters($request);
        $data = $this->service->build($filters);
        $view = in_array($request->query('view'), ['summary', 'beneficiaries', 'services'], true)
            ? (string) $request->query('view')
            : 'summary';

        return view('admin.legacy-data.index', array_merge($data, [
            'filters' => $filters,
            'viewMode' => $view,
            'groups' => self::GROUPS,
            'beneficiaries' => $this->paginate($data['rows'], $request, 'beneficiary_page'),
            'services' => $this->paginate($data['service_rows'], $request, 'service_page'),
            'showMobile' => $request->boolean('show_mobile'),
        ]));
    }

    public function refresh(): RedirectResponse
    {
        @set_time_limit(180);
        $this->service->refresh();

        return redirect()->route('admin.legacy-data.index')
            ->with('status', 'Legacy Data refreshed from all available phase databases.');
    }

    public function mappings(Request $request): View
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
        $query = mb_strtolower(trim((string) $request->query('q', '')));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'mapped', 'unmapped'], true)) {
            $status = 'all';
        }

        $all = $this->service->mappingInventory();
        $rows = $all->filter(function (array $row) use ($query, $status): bool {
            if ($status === 'mapped' && ! $row['mapped']) {
                return false;
            }
            if ($status === 'unmapped' && $row['mapped']) {
                return false;
            }
            if ($query !== '' && ! str_contains(mb_strtolower($row['original_name'].' '.$row['standard_name'].' '.$row['phase']), $query)) {
                return false;
            }

            return true;
        })->values();

        return view('admin.legacy-data.mappings', [
            'rows' => $this->paginate($rows, $request, 'page', 40),
            'services' => $this->serviceNames->availableServices(),
            'filters' => ['q' => (string) $request->query('q', ''), 'status' => $status],
            'stats' => [
                'total' => $all->count(),
                'mapped' => $all->where('mapped', true)->count(),
                'unmapped' => $all->where('mapped', false)->count(),
                'records_unmapped' => $all->where('mapped', false)->sum('records'),
            ],
        ]);
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_phase' => ['required', Rule::in(['phase1', 'phase2'])],
            'original_name' => ['required', 'string', 'max:255'],
            'service_id' => ['required', Rule::exists('services', 'id')],
        ]);

        $normalized = $this->serviceNames->normalizeKey($validated['original_name']);
        $alias = LegacyServiceAlias::query()->firstOrNew([
            'source_phase' => $validated['source_phase'],
            'normalized_hash' => sha1($normalized),
        ]);
        $before = $alias->exists ? [
            'source_phase' => $alias->source_phase,
            'original_name' => $alias->original_name,
            'service_id' => $alias->service_id,
            'is_active' => $alias->is_active,
        ] : null;

        $alias->fill([
            'original_name' => trim($validated['original_name']),
            'normalized_name' => $normalized,
            'service_id' => (int) $validated['service_id'],
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);
        if (! $alias->exists) {
            $alias->created_by = auth()->id();
        }
        $alias->save();

        $this->auditLogger->record(
            $request,
            $before ? 'legacy_service_alias.updated' : 'legacy_service_alias.created',
            LegacyServiceAlias::class,
            $alias->id,
            $before,
            [
                'source_phase' => $alias->source_phase,
                'original_name' => $alias->original_name,
                'service_id' => $alias->service_id,
                'is_active' => true,
            ],
            'Legacy service mapped to the Phase 3 service master',
        );
        $this->service->refresh();

        return back()->with('status', 'Mapping saved. Phase 3 service data was not changed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $data = $this->service->build($filters);
        $exportServices = $request->query('view') === 'services';
        $rows = $exportServices ? $data['service_rows'] : $data['rows'];
        $filename = 'legacy-data-'.($exportServices ? 'services-' : 'onboarded-').now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $exportServices): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            if ($exportServices) {
                fputcsv($out, ['FY', 'Phase', 'Application No', 'Applicant', 'Mobile', 'District', 'Business Category', 'Standard Phase 3 Service', 'Original Source Service', 'Mapping', 'Detail', 'Status', 'Delivery/Event Date']);
                foreach ($rows as $row) {
                    fputcsv($out, [$row['financial_year'], $row['phase'], $row['application_no'], $row['applicant'], $row['phone'], $row['district'], $row['business_category'], $row['service'], $row['original_service'], $row['mapping_source'], $row['service_detail'], $row['service_status'], $row['service_date']]);
                }
            } else {
                fputcsv($out, ['FY', 'Phase', 'Application No', 'Applicant', 'Mobile', 'District', 'Block', 'Beneficiary Type', 'Business Category', 'Business Stage', 'Gender', 'Education', 'Onboarding Date', 'Approved Services Count', 'Standard Services', 'Original Source Services']);
                foreach ($rows as $row) {
                    fputcsv($out, [$row['financial_year'], $row['phase'], $row['application_no'], $row['applicant'], $row['phone'], $row['district'], $row['block'], $row['beneficiary_type'], $row['business_category'], $row['business_stage'], $row['gender'], $row['education'], $row['onboarding_date'], $row['filtered_services_count'], $row['services'], $row['original_services']]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        $group = (string) $request->query('group', 'district');
        if (! array_key_exists($group, self::GROUPS)) {
            $group = 'district';
        }

        return [
            'fy' => trim((string) $request->query('fy', '')),
            'phase' => trim((string) $request->query('phase', '')),
            'district' => trim((string) $request->query('district', '')),
            'service' => trim((string) $request->query('service', '')),
            'service_status' => trim((string) $request->query('service_status', '')),
            'category' => trim((string) $request->query('category', '')),
            'stage' => trim((string) $request->query('stage', '')),
            'gender' => trim((string) $request->query('gender', '')),
            'education' => trim((string) $request->query('education', '')),
            'type' => trim((string) $request->query('type', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'q' => trim((string) $request->query('q', '')),
            'group' => $group,
        ];
    }

    private function paginate(Collection $rows, Request $request, string $pageName, int $perPage = 30): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName],
        );
    }
}
