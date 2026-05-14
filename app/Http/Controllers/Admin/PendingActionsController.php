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
        $countableStatuses = [
            ServiceCase::STATUS_PENDING_APPROVAL,
            ServiceCase::STATUS_SENT_BACK,
            ServiceCase::STATUS_APPROVED,
            ServiceCase::STATUS_REJECTED,
        ];

        $submittedBase = ServiceCase::query()
            ->whereIn('status', $countableStatuses)
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));

        $base = ServiceCase::query()
            ->where('status', ServiceCase::STATUS_PENDING_APPROVAL)
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));

        $applyDistrictFilter = function ($query) use ($filterDistrictId) {
            if ($filterDistrictId > 0) {
                $query->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $filterDistrictId));
            }

            return $query;
        };

        $totalPending = $applyDistrictFilter(clone $base)->count();
        $totalSubmissions = $applyDistrictFilter(clone $submittedBase)->count();
        $pendingRate = $totalSubmissions > 0
            ? round(($totalPending / $totalSubmissions) * 100, 1)
            : 0.0;
        $totalUniqueApplications = $applyDistrictFilter(clone $submittedBase)->distinct('cfa_submission_id')->count('cfa_submission_id');
        $pendingUniqueApplications = $applyDistrictFilter(clone $base)->distinct('cfa_submission_id')->count('cfa_submission_id');
        $affectedApplicationRate = $totalUniqueApplications > 0
            ? round(($pendingUniqueApplications / $totalUniqueApplications) * 100, 1)
            : 0.0;
        $districtsImpacted = $applyDistrictFilter(clone $base)
            ->join('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
            ->distinct('cs.district_id')
            ->count('cs.district_id');

        $oldestPendingAt = $applyDistrictFilter(clone $base)->min('submitted_at');
        $avgPendingDays = (int) round($applyDistrictFilter(clone $base)
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, COALESCE(submitted_at, created_at), NOW())) as avg_days')
            ->value('avg_days') ?? 0);

        $spocStatsRows = $applyDistrictFilter(clone $base)
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

        $districtStats = $applyDistrictFilter(clone $base)
            ->join('cfa_submissions as cs', 'cs.id', '=', 'service_cases.cfa_submission_id')
            ->join('districts as d', 'd.id', '=', 'cs.district_id')
            ->selectRaw('d.id as district_id, d.name as district_name, COUNT(*) as pending_count')
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('pending_count')
            ->get();

        $assignedBase = ServiceCase::query()
            ->whereNotNull('spoc_user_id')
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));
        $approvedBase = ServiceCase::query()
            ->whereNotNull('approved_by')
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));
        $rejectedBase = ServiceCase::query()
            ->whereNotNull('rejected_by')
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));

        $sentBackRows = ServiceCase::query()
            ->selectRaw('service_case_events.user_id as spoc_id, COUNT(*) as sent_back_count')
            ->join('service_case_events', 'service_case_events.service_case_id', '=', 'service_cases.id')
            ->where('service_case_events.action', 'spoc_sent_back')
            ->whereHas('submitter', fn ($q) => $q->where('role', 'district_staff'));
        $assignedRows = $applyDistrictFilter(clone $assignedBase)
            ->selectRaw('spoc_user_id as spoc_id, COUNT(*) as entries_received')
            ->groupBy('spoc_user_id')
            ->get();
        $approvedRows = $applyDistrictFilter(clone $approvedBase)
            ->selectRaw('approved_by as spoc_id, COUNT(*) as approved_count')
            ->groupBy('approved_by')
            ->get();
        $rejectedRows = $applyDistrictFilter(clone $rejectedBase)
            ->selectRaw('rejected_by as spoc_id, COUNT(*) as rejected_count')
            ->groupBy('rejected_by')
            ->get();
        $sentBackRows = $applyDistrictFilter($sentBackRows)
            ->groupBy('service_case_events.user_id')
            ->get();

        $performanceIds = collect()
            ->merge($assignedRows->pluck('spoc_id'))
            ->merge($approvedRows->pluck('spoc_id'))
            ->merge($rejectedRows->pluck('spoc_id'))
            ->merge($sentBackRows->pluck('spoc_id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $performanceNames = User::query()
            ->whereIn('id', $performanceIds->all())
            ->pluck('name', 'id');

        $entriesMap = $assignedRows->pluck('entries_received', 'spoc_id');
        $approvedMap = $approvedRows->pluck('approved_count', 'spoc_id');
        $rejectedMap = $rejectedRows->pluck('rejected_count', 'spoc_id');
        $sentBackMap = $sentBackRows->pluck('sent_back_count', 'spoc_id');
        $pendingMap = $spocStats->pluck('pending_count', 'spoc_id');

        $spocPerformance = $performanceIds
            ->map(function (int $spocId) use ($performanceNames, $entriesMap, $approvedMap, $rejectedMap, $sentBackMap, $pendingMap): array {
                $entriesReceived = (int) ($entriesMap[$spocId] ?? 0);
                $approved = (int) ($approvedMap[$spocId] ?? 0);
                $sentBack = (int) ($sentBackMap[$spocId] ?? 0);
                $rejected = (int) ($rejectedMap[$spocId] ?? 0);
                $pending = (int) ($pendingMap[$spocId] ?? 0);

                return [
                    'spoc_id' => $spocId,
                    'spoc_name' => (string) ($performanceNames[$spocId] ?? 'Unknown SPOC'),
                    'entries_received' => $entriesReceived,
                    'approved_count' => $approved,
                    'sent_back_count' => $sentBack,
                    'rejected_count' => $rejected,
                    'pending_count' => $pending,
                ];
            })
            ->sortByDesc('entries_received')
            ->values();

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
            'totalSubmissions' => $totalSubmissions,
            'totalPending' => $totalPending,
            'pendingRate' => $pendingRate,
            'pendingUniqueApplications' => $pendingUniqueApplications,
            'totalUniqueApplications' => $totalUniqueApplications,
            'affectedApplicationRate' => $affectedApplicationRate,
            'districtsImpacted' => $districtsImpacted,
            'spocsWithPending' => $spocStats->where('spoc_id', '>', 0)->count(),
            'avgPendingDays' => $avgPendingDays,
            'oldestPendingAt' => $oldestPendingAt,
            'spocStats' => $spocStats,
            'spocPerformance' => $spocPerformance,
            'districtStats' => $districtStats,
            'cases' => $cases,
            'districtOptions' => $districtOptions,
            'filterSpocId' => $filterSpocId,
            'filterDistrictId' => $filterDistrictId,
        ]);
    }
}

