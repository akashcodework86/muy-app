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
        // Default this page to FY 2025-26 (the legacy Phase 2 data year) when no explicit FY is chosen.
        if ($request->query('fiscal_year_id')) {
            $requestedFyId = (int) $request->query('fiscal_year_id');
        } else {
            $fy2526Id = (int) (FiscalYear::query()->where('code', '2025-26')->value('id') ?? 0);
            $requestedFyId = $fy2526Id > 0 ? $fy2526Id : null;
        }

        [$fiscalYearId, $fiscalYears] = FiscalYear::resolveIdForUi($requestedFyId);

        if ($fiscalYears->isEmpty()) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => 0,
                'fiscalYear' => null,
                'districts' => [],
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
                'districts' => [],
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
                'districts' => [],
                'legacyUnavailable' => true,
                'legacyMissingTables' => false,
            ]);
        }

        try {
            $hasTables = Schema::connection('legacy')->hasTable('rbi_applications')
                && Schema::connection('legacy')->hasTable('rbi_applicant_details');
        } catch (\Exception $e) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => $fiscalYearId,
                'fiscalYear' => $fiscalYear,
                'districts' => [],
                'legacyUnavailable' => true,
                'legacyMissingTables' => false,
            ]);
        }

        if (! $hasTables) {
            return view('admin.phase2-cfa.index', [
                'rows' => $this->emptyPaginator(),
                'fiscalYears' => $fiscalYears,
                'fiscalYearId' => $fiscalYearId,
                'fiscalYear' => $fiscalYear,
                'districts' => [],
                'legacyUnavailable' => false,
                'legacyMissingTables' => true,
            ]);
        }

        $start = Carbon::parse($fiscalYear->starts_on)->toDateString();
        $end = Carbon::parse($fiscalYear->ends_on)->toDateString();

        // Distinct districts for filter dropdown
        $districts = DB::connection('legacy')
            ->table('rbi_applicant_details')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->orderBy('district')
            ->pluck('district')
            ->toArray();

        $query = DB::connection('legacy')
            ->table('rbi_applications as a')
            ->leftJoin('rbi_applicant_details as d', 'd.application_id', '=', 'a.id')
            ->whereNotNull('a.submission_date')
            ->whereBetween(DB::raw('DATE(a.submission_date)'), [$start, $end])
            ->select([
                'a.id as legacy_id',
                'a.application_no',
                'a.category',
                'a.form_stage',
                'a.submission_date',
                'd.applicant_name',
                'd.phone',
                'd.district',
                'd.block',
                'd.gender',
                'd.submitted_by_name',
            ]);

        // Filter: district
        if ($request->filled('district')) {
            $query->where('d.district', $request->input('district'));
        }

        // Filter: search by applicant name, phone, application no., or numeric application id
        if ($request->filled('search')) {
            $raw = trim((string) $request->input('search'));
            $search = '%'.$raw.'%';
            $query->where(function ($q) use ($search, $raw) {
                $q->where('d.applicant_name', 'like', $search)
                    ->orWhere('d.phone', 'like', $search)
                    ->orWhere('a.application_no', 'like', $search);
                if ($raw !== '' && ctype_digit($raw)) {
                    $id = (int) $raw;
                    $q->orWhere('a.id', $id)
                        ->orWhere('d.application_id', $id);
                }
            });
        }

        $query->orderByDesc('a.submission_date')->orderByDesc('a.id');

        $rows = $query->paginate(100)->withQueryString();

        return view('admin.phase2-cfa.index', [
            'rows' => $rows,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $fiscalYearId,
            'fiscalYear' => $fiscalYear,
            'districts' => $districts,
            'legacyUnavailable' => false,
            'legacyMissingTables' => false,
        ]);
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 100, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
