<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\LegacyPhase2\LegacyPhase2ListQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyPhase2CfaApplicationController extends Controller
{
    /**
     * Read-only list of Call for Application rows from the legacy Phase 2 DB (`rbi_applications`),
     * filtered by the same FY window as targets.php (submission_date between fiscal_years.starts_on … ends_on).
     */
    public function index(Request $request): View
    {
        [$fiscalYearId, $fiscalYears, $fiscalYear] = $this->resolveFiscalYearContext($request);
        $districts = LegacyPhase2ListQuery::canonicalDistricts();

        if ($fiscalYears->isEmpty()) {
            return view('admin.phase2-cfa.index', $this->emptyViewData($fiscalYears, $districts));
        }

        if ($fiscalYear === null) {
            return view('admin.phase2-cfa.index', $this->emptyViewData($fiscalYears, $districts));
        }

        if ((string) config('database.connections.legacy.database', '') === '') {
            return view('admin.phase2-cfa.index', array_merge($this->emptyViewData($fiscalYears, $districts, $fiscalYearId, $fiscalYear), [
                'legacyUnavailable' => true,
            ]));
        }

        try {
            $hasTables = Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Exception $e) {
            return view('admin.phase2-cfa.index', array_merge($this->emptyViewData($fiscalYears, $districts, $fiscalYearId, $fiscalYear), [
                'legacyUnavailable' => true,
            ]));
        }

        if (! $hasTables) {
            return view('admin.phase2-cfa.index', array_merge($this->emptyViewData($fiscalYears, $districts, $fiscalYearId, $fiscalYear), [
                'legacyMissingTables' => true,
            ]));
        }

        [$start, $end] = LegacyPhase2ListQuery::fyWindowDates($fiscalYear);
        $scopeCounts = LegacyPhase2ListQuery::scopeCounts($request, $start, $end);
        $filterOptions = LegacyPhase2ListQuery::filterOptions($start, $end);

        $query = LegacyPhase2ListQuery::listQueryForFyWindow($start, $end);
        LegacyPhase2ListQuery::applyFilters($query, $request);
        $query->orderByDesc('a.submission_date')->orderByDesc('a.id');

        $rows = $query
            ->paginate(100)
            ->withQueryString()
            ->through(fn ($row) => LegacyPhase2ListQuery::enrichRow($row));

        return view('admin.phase2-cfa.index', [
            'rows' => $rows,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'districts' => $districts,
            'filterOptions' => $filterOptions,
            'scopeCounts' => $scopeCounts,
            'legacyUnavailable' => false,
            'legacyMissingTables' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$fiscalYearId, , $fiscalYear] = $this->resolveFiscalYearContext($request);

        abort_if($fiscalYear === null, 422, 'No fiscal year configured.');
        abort_if((string) config('database.connections.legacy.database', '') === '', 422, 'Legacy database is not configured.');

        try {
            abort_unless(
                Schema::connection('legacy')->hasTable('rbi_applications')
                    && Schema::connection('legacy')->hasTable('rbi_applicant_details'),
                422,
                'Required legacy tables were not found.'
            );
        } catch (\Exception $e) {
            abort(422, 'Legacy database is not available.');
        }

        [$start, $end] = LegacyPhase2ListQuery::fyWindowDates($fiscalYear);
        $query = LegacyPhase2ListQuery::exportQueryForFyWindow($start, $end);
        LegacyPhase2ListQuery::applyFilters($query, $request);
        $query->orderByDesc('a.submission_date')->orderByDesc('a.id');

        $headers = LegacyPhase2ListQuery::exportHeaderLabels();
        $fySlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($fiscalYear->code ?? 'fy'))) ?: 'fy';
        $filename = 'cfa-phase2-legacy-full-'.$fySlug.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            $srNo = 0;
            foreach ($query->cursor() as $row) {
                $srNo++;
                fputcsv($out, LegacyPhase2ListQuery::exportRowValues($row, $srNo));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: int, 1: Collection<int, FiscalYear>, 2: ?FiscalYear}
     */
    private function resolveFiscalYearContext(Request $request): array
    {
        if ($request->query('fiscal_year_id')) {
            $requestedFyId = (int) $request->query('fiscal_year_id');
        } else {
            $fy2526Id = (int) (FiscalYear::query()->where('code', '2025-26')->value('id') ?? 0);
            $requestedFyId = $fy2526Id > 0 ? $fy2526Id : null;
        }

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi($requestedFyId);
        $fiscalYear = $fiscalYearId > 0 ? FiscalYear::query()->find($fiscalYearId) : null;

        return [$fiscalYearId, $fiscalYears, $fiscalYear];
    }

    /**
     * @param  Collection<int, FiscalYear>  $fiscalYears
     * @param  list<string>  $districts
     * @return array<string, mixed>
     */
    private function emptyViewData(
        $fiscalYears,
        array $districts,
        int $fiscalYearId = 0,
        ?FiscalYear $fiscalYear = null,
    ): array {
        return [
            'rows' => $this->emptyPaginator(),
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'districts' => $districts,
            'filterOptions' => ['categories' => [], 'form_stages' => [], 'genders' => []],
            'scopeCounts' => ['total' => 0, 'onboarded' => 0, 'non_onboarded' => 0],
            'legacyUnavailable' => false,
            'legacyMissingTables' => false,
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
