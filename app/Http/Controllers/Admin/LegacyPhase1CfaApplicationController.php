<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use App\Services\LegacyPhase1\LegacyPhase1ListQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyPhase1CfaApplicationController extends Controller
{
    /**
     * Read-only list of CFA rows from the legacy Phase 1 DB (`tblapplication`).
     */
    public function index(Request $request): View
    {
        $districts = LegacyPhase1DistrictResolver::canonicalDistricts();

        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return view('admin.phase1-cfa.index', $this->emptyViewData($districts, phase1Unavailable: true));
        }

        try {
            $hasTable = Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Exception $e) {
            return view('admin.phase1-cfa.index', $this->emptyViewData($districts, phase1Unavailable: true));
        }

        if (! $hasTable) {
            return view('admin.phase1-cfa.index', $this->emptyViewData($districts, phase1MissingTables: true));
        }

        $scopeCounts = LegacyPhase1ListQuery::scopeCounts($request);

        $query = LegacyPhase1ListQuery::listQuery();
        LegacyPhase1ListQuery::applyFilters($query, $request);
        $query->orderByDesc('ApplicationDate')->orderByDesc('ID');

        $rows = $query
            ->paginate(100)
            ->withQueryString()
            ->through(fn ($row) => LegacyPhase1DistrictResolver::enrichRow($row));

        return view('admin.phase1-cfa.index', [
            'rows' => $rows,
            'districts' => $districts,
            'filterOptions' => LegacyPhase1ListQuery::filterOptions(),
            'scopeCounts' => $scopeCounts,
            'phase1Unavailable' => false,
            'phase1MissingTables' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_if((string) config('database.connections.legacy_phase1.database', '') === '', 422, 'Phase 1 database is not configured.');

        try {
            abort_unless(Schema::connection('legacy_phase1')->hasTable('tblapplication'), 422, 'Required Phase 1 table was not found.');
        } catch (\Exception $e) {
            abort(422, 'Phase 1 database is not available.');
        }

        $query = LegacyPhase1ListQuery::listQuery();
        LegacyPhase1ListQuery::applyFilters($query, $request);
        $rows = $query->orderByDesc('ApplicationDate')->orderByDesc('ID')->get()
            ->map(fn ($row) => LegacyPhase1DistrictResolver::enrichRow($row));

        $filename = 'cfa-phase1-legacy-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Sr No',
                'Application No',
                'Application Date',
                'Applicant',
                'Mobile',
                'District',
                'Legacy Region',
                'Village',
                'onboard_status',
                'Loan Scheme Status',
                'Gender',
                'Education',
            ]);
            foreach ($rows->values() as $idx => $row) {
                $phone = (string) ($row->mobile_number ?? '');
                if ($phone !== '' && preg_match('/^\d{10,}$/', $phone)) {
                    $phone = "\t".$phone;
                }
                fputcsv($out, [
                    (string) ($idx + 1),
                    (string) ($row->application_no ?? ''),
                    $row->application_date ? (string) $row->application_date : '',
                    (string) ($row->full_name ?? ''),
                    $phone,
                    (string) ($row->district_name ?? ''),
                    (string) ($row->legacy_region ?? ''),
                    (string) ($row->city_name ?? ''),
                    (string) ($row->onboard_label ?? 'Non onboarded'),
                    (string) ($row->application_status ?? ''),
                    (string) ($row->gender ?? ''),
                    (string) ($row->education ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $districts
     * @return array<string, mixed>
     */
    private function emptyViewData(
        array $districts,
        bool $phase1Unavailable = false,
        bool $phase1MissingTables = false,
    ): array {
        return [
            'rows' => $this->emptyPaginator(),
            'districts' => $districts,
            'filterOptions' => LegacyPhase1ListQuery::filterOptions(),
            'scopeCounts' => ['total' => 0, 'onboarded' => 0, 'non_onboarded' => 0],
            'phase1Unavailable' => $phase1Unavailable,
            'phase1MissingTables' => $phase1MissingTables,
        ];
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 100, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
