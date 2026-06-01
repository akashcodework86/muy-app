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

class LegacyPhase2CfaApplicationController extends Controller
{
    /**
     * Read-only list of Call for Application rows from the legacy Phase 2 DB (`rbi_applications`),
     * filtered by the same FY window as targets.php (submission_date between fiscal_years.starts_on … ends_on).
     */
    public function index(Request $request): View
    {
        if ($request->query('fiscal_year_id')) {
            $requestedFyId = (int) $request->query('fiscal_year_id');
        } else {
            $fy2526Id = (int) (FiscalYear::query()->where('code', '2025-26')->value('id') ?? 0);
            $requestedFyId = $fy2526Id > 0 ? $fy2526Id : null;
        }

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi($requestedFyId);
        $districts = LegacyPhase2ListQuery::canonicalDistricts();

        if ($fiscalYears->isEmpty()) {
            return view('admin.phase2-cfa.index', $this->emptyViewData($fiscalYears, $districts));
        }

        $fiscalYear = FiscalYear::query()->find($fiscalYearId);
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
