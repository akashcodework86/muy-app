<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PendingActionsController extends Controller
{
    public function index(Request $request): View
    {
        $filterSpocId = (int) $request->query('spoc_id', 0);
        $filterDistrictId = (int) $request->query('district_id', 0);

        $base = ServiceCase::query()
            ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));

        $totalPending = (clone $base)->count();
        $districtsImpacted = (clone $base)
            ->join('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
            ->distinct('cs.district_id')
            ->count('cs.district_id');

        $oldestPendingAt = (clone $base)->min('submitted_at');
        $avgPendingDays = (int) round((clone $base)
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, COALESCE(submitted_at, created_at), NOW())) as avg_days')
            ->value('avg_days') ?? 0);

        $spocStatsRows = (clone $base)
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
            ->selectRaw('COALESCE(service_cases.spoc_user_id, 0) as spoc_id, COUNT(*) as pending_count, COUNT(DISTINCT cs.district_id) as district_count')
            ->groupBy(DB::raw('COALESCE(service_cases.spoc_user_id, 0)'))
            ->orderByDesc('pending_count')
            ->get();

        $spocIds = $spocStatsRows
            ->pluck('spoc_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $spocNames = User::query()
            ->whereIn('id', $spocIds)
            ->pluck('name', 'id');

        $spocStats = $spocStatsRows->map(function ($row) use ($spocNames): array {
            $id = (int) $row->spoc_id;

            return [
                'spoc_id' => $id,
                'spoc_name' => $id > 0 ? (string) ($spocNames[$id] ?? 'Unknown SPOC') : 'Unassigned',
                'pending_count' => (int) $row->pending_count,
                'district_count' => (int) $row->district_count,
            ];
        });

        $districtStats = (clone $base)
            ->join('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw('d.id as district_id, d.name as district_name, COUNT(*) as pending_count')
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('pending_count')
            ->get();

        $caseQuery = (clone $base)
            ->with([
                'service:id,name',
                'cfaSubmission:id,application_no,applicant_name,district_id',
                'cfaSubmission.district:id,name',
                'submitter:id,name',
                'spoc:id,name',
            ])
            ->orderByDesc('updated_at');

        if ($filterSpocId > 0) {
            $caseQuery->where('spoc_user_id', $filterSpocId);
        } elseif ($filterSpocId === -1) {
            $caseQuery->whereNull('spoc_user_id');
        }
        if ($filterDistrictId > 0) {
            $caseQuery->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $filterDistrictId));
        }

        $cases = $caseQuery->paginate(25)->withQueryString();

        $districtOptions = District::query()
            ->whereIn('id', $districtStats->pluck('district_id')->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.pending-actions.index', [
            'totalPending' => $totalPending,
            'districtsImpacted' => $districtsImpacted,
            'spocsWithPending' => $spocStats->where('spoc_id', '>', 0)->count(),
            'avgPendingDays' => $avgPendingDays,
            'oldestPendingAt' => $oldestPendingAt,
            'spocStats' => $spocStats,
            'districtStats' => $districtStats,
            'cases' => $cases,
            'districtOptions' => $districtOptions,
            'filterSpocId' => $filterSpocId,
            'filterDistrictId' => $filterDistrictId,
        ]);
    }
}

