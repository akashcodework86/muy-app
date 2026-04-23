<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\FieldVisitReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class FieldVisitReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = FieldVisitReport::query()
            ->with(['user.district', 'district', 'block'])
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');

        if ($request->filled('coordinator_id')) {
            $query->where('user_id', $request->integer('coordinator_id'));
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }

        if ($request->filled('block_id')) {
            $query->where('block_id', $request->integer('block_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->input('date_to'));
        }

        if ($request->boolean('only_discrepancies')) {
            $query->where('has_discrepancy', true);
        }

        $reports = $query->paginate(50)->withQueryString();

        // Summary stats (un-filtered for tiles, filtered for table)
        $fieldCoordinatorUserIds = User::where('role', 'district_staff')->pluck('id');

        $totalCoordinators  = $fieldCoordinatorUserIds->count();
        $activeCoordinators = User::where('role', 'district_staff')->where('is_active', true)->count();

        $totalCfasVerified = DB::table('cfa_submissions')
            ->whereIn('referral_user_id', $fieldCoordinatorUserIds)
            ->count();

        $discrepancyCount = FieldVisitReport::where('has_discrepancy', true)->count();

        $totalParticipants = FieldVisitReport::sum('total_participants');

        $districts    = District::orderBy('name')->get();
        $coordinators = User::where('role', 'district_staff')
            ->orderBy('name')
            ->get(['id', 'name', 'district_id']);

        $blocks = $request->filled('district_id')
            ? DistrictBlock::where('district_id', $request->integer('district_id'))->orderBy('name')->get()
            : collect();

        return view('admin.field-visit-reports.index', compact(
            'reports',
            'districts',
            'coordinators',
            'blocks',
            'totalCoordinators',
            'activeCoordinators',
            'totalCfasVerified',
            'discrepancyCount',
            'totalParticipants',
        ));
    }

    public function export(Request $request)
    {
        $query = FieldVisitReport::query()
            ->with(['user', 'district', 'block'])
            ->orderByDesc('visit_date');

        if ($request->filled('coordinator_id')) {
            $query->where('user_id', $request->integer('coordinator_id'));
        }
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->input('date_to'));
        }
        if ($request->boolean('only_discrepancies')) {
            $query->where('has_discrepancy', true);
        }

        $rows = $query->get();

        $csvLines = [];
        $csvLines[] = implode(',', [
            'Date of Visit', 'Coordinator', 'District', 'Block', 'Area',
            'Villages Visited', 'Village Names', 'Participants',
            'Outreach Programmes', 'CFAs Reported', 'CFAs Verified', 'Discrepancy',
        ]);

        foreach ($rows as $r) {
            $csvLines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
                [
                    $r->visit_date?->format('d-m-Y'),
                    $r->user?->name,
                    $r->district?->name,
                    $r->block?->name,
                    $r->area,
                    $r->total_villages,
                    $r->village_names,
                    $r->total_participants,
                    $r->outreach_programmes,
                    $r->cfas_reported,
                    $r->cfas_verified ?? 'N/A',
                    $r->has_discrepancy ? 'YES' : 'No',
                ]
            ));
        }

        $csv = implode("\n", $csvLines);
        $filename = 'field-visit-reports-' . now()->format('Y-m-d') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
