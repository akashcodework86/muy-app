<?php

namespace App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HubApplicationsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'hub_admin' && $user->hub_id, 403);

        $hubId = (int) $user->hub_id;
        $districts = District::query()
            ->where('hub_id', $hubId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $districtIds = $districts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $activeFy = FiscalYear::phase3Default();

        $staff = User::query()
            ->where('role', 'district_staff')
            ->where('hub_id', $hubId)
            ->orderBy('name')
            ->get(['id', 'name', 'district_id', 'referral_token']);

        $filters = $this->extractFilters($request);
        $query = $this->filteredQuery(
            districtIds: $districtIds,
            activeFyId: (int) optional($activeFy)->id,
            filters: $filters
        );

        $applications = $query->paginate(40)->withQueryString();

        $sourceCounts = CfaSubmission::query()
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($activeFy, fn ($q) => $q->where('fiscal_year_id', (int) $activeFy->id))
            ->selectRaw('COALESCE(source, "unknown") as src, COUNT(*) as total')
            ->groupBy('src')
            ->pluck('total', 'src');

        return view('hub.applications.index', [
            'applications' => $applications,
            'districts' => $districts,
            'staff' => $staff,
            'sourceCounts' => $sourceCounts,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'hub_admin' && $user->hub_id, 403);

        $hubId = (int) $user->hub_id;
        $districtIds = District::query()
            ->where('hub_id', $hubId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $activeFyId = (int) optional(FiscalYear::phase3Default())->id;
        $filters = $this->extractFilters($request);

        $query = $this->filteredQuery(
            districtIds: $districtIds,
            activeFyId: $activeFyId,
            filters: $filters
        )->with(['district:id,name', 'referralUser:id,name']);

        $columns = Schema::getColumnListing('cfa_submissions');
        // chunkById orders by id; clear filteredQuery()'s orderByDesc so chunks do not skip rows.
        $payloadColumnsMap = $this->discoverPayloadColumns((clone $query)->reorder());
        $payloadColumns = array_keys($payloadColumnsMap);
        $baseColumns = array_values(array_filter($columns, fn (string $c) => $c !== 'payload'));
        $extraColumns = ['district_name', 'referral_staff_name', 'exported_at_ist', 'payload_json'];
        $headers = array_merge($baseColumns, $extraColumns, $payloadColumns);

        $filename = 'hub-cfa-full-export-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $headers, $baseColumns, $payloadColumnsMap): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, $headers);

            (clone $query)->reorder()->chunkById(500, function ($rows) use ($out, $baseColumns, $payloadColumnsMap): void {
                foreach ($rows as $row) {
                    $record = [];
                    foreach ($baseColumns as $column) {
                        $record[] = $this->toCsvValue($row->{$column} ?? null);
                    }
                    $record[] = $row->district?->name;
                    $record[] = $row->referralUser?->name;
                    $record[] = optional($row->created_at)->timezone('Asia/Kolkata')->format('d M Y h:i A');
                    $payload = is_array($row->payload) ? $row->payload : (array) $row->payload;
                    $record[] = $this->toCsvValue($payload);
                    foreach ($payloadColumnsMap as $payloadColumn => $originalKey) {
                        $record[] = $this->toCsvValue($payload[$originalKey] ?? null);
                    }

                    fputcsv($out, $record);
                }
            }, 'id');

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{staff_id:int|null,district_id:int|null,source:string,q:string,from:string,to:string}
     */
    private function extractFilters(Request $request): array
    {
        $source = trim((string) $request->query('source', ''));
        $allowedSources = ['referral', 'not_linked'];
        if (! in_array($source, $allowedSources, true)) {
            $source = '';
        }

        return [
            'staff_id' => $request->integer('staff_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'source' => $source,
            'q' => trim((string) $request->query('q', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    /**
     * @param  list<int>  $districtIds
     * @param  array{staff_id:int|null,district_id:int|null,source:string,q:string,from:string,to:string}  $filters
     */
    private function filteredQuery(array $districtIds, int $activeFyId, array $filters)
    {
        return CfaSubmission::query()
            ->with(['district:id,name', 'referralUser:id,name,referral_token'])
            ->when($districtIds !== [], fn ($q) => $q->whereIn('district_id', $districtIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($activeFyId > 0, fn ($q) => $q->where('fiscal_year_id', $activeFyId))
            ->when($filters['district_id'], fn ($q) => $q->where('district_id', (int) $filters['district_id']))
            ->when($filters['staff_id'], fn ($q) => $q->where('referral_user_id', (int) $filters['staff_id']))
            ->when($filters['source'] === 'referral', fn ($q) => $q->whereNotNull('referral_user_id'))
            ->when($filters['source'] === 'not_linked', fn ($q) => $q->whereNull('referral_user_id'))
            ->when($filters['q'] !== '', function ($qBuilder) use ($filters): void {
                $term = $filters['q'];
                $qBuilder->where(function ($inner) use ($term): void {
                    $inner->where('application_no', 'like', "%{$term}%")
                        ->orWhere('applicant_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at');
    }

    private function toCsvValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function discoverPayloadColumns($query): array
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
}

