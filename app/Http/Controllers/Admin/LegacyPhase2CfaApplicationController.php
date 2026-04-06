<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi(
            $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null
        );

        if ($fiscalYears->isEmpty()) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => 0,
                'fiscalYear' => null,
                'legacyUnavailable' => false,
                'legacyMissingTables' => false,
            ]);
        }

        $fiscalYear = FiscalYear::query()->find($fiscalYearId);
        if ($fiscalYear === null) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => 0,
                'fiscalYear' => null,
                'legacyUnavailable' => false,
                'legacyMissingTables' => false,
            ]);
        }

        if ((string) config('database.connections.legacy.database', '') === '') {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => $fiscalYearId,
                'fiscalYear' => $fiscalYear,
                'legacyUnavailable' => true,
                'legacyMissingTables' => false,
            ]);
        }

        if (! Schema::connection('legacy')->hasTable('rbi_applications')
            || ! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => $fiscalYearId,
                'fiscalYear' => $fiscalYear,
                'legacyUnavailable' => false,
                'legacyMissingTables' => true,
            ]);
        }

        $start = Carbon::parse($fiscalYear->starts_on)->toDateString();
        $end = Carbon::parse($fiscalYear->ends_on)->toDateString();

        $rows = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->orderByDesc('a.submission_date')
            ->orderByDesc('a.id')
            ->select([
                'a.id as legacy_id',
                'a.application_no',
                'a.submission_date',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'd.submitted_by_name',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('admin.phase2-cfa.index', [
            'rows' => $rows,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'legacyUnavailable' => false,
            'legacyMissingTables' => false,
        ]);
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 25, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
