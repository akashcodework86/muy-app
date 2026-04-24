<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceAdminController extends Controller
{
    public function index(Request $request): View
    {
        // All district_staff users — shown in coordinator dropdown regardless of attendance history
        $coordinators = User::query()
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->with('district:id,name')
            ->select('id', 'name', 'district_id')
            ->orderBy('name')
            ->get();

        $query = FieldCoordinatorAttendanceReport::query()
            ->with(['coordinator:id,name', 'district:id,name']);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('field_coordinator_name', 'like', $like)
                    ->orWhere('area', 'like', $like)
                    ->orWhere('block', 'like', $like);
            });
        }

        // Coordinator dropdown filter
        $coordinatorId = (int) $request->query('coordinator_id', 0);
        if ($coordinatorId > 0) {
            $query->where('field_coordinator_user_id', $coordinatorId);
        }

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', (string) $request->query('to'));
        }

        $reports = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // Build CFA map keyed by "userId_date" => count
        $pairs = $reports->getCollection()->map(
            fn ($r) => [
                'uid'  => (int) $r->field_coordinator_user_id,
                'date' => $r->visit_date?->format('Y-m-d'),
            ]
        )->filter(fn ($p) => $p['date'] !== null)->values();

        $cfaMap = [];
        if ($pairs->isNotEmpty()) {
            $userIds   = $pairs->pluck('uid')->unique()->values()->all();
            $dates     = $pairs->pluck('date')->unique()->values()->all();

            $rows = CfaSubmission::query()
                ->whereIn('referral_user_id', $userIds)
                ->whereIn(DB::raw('DATE(created_at)'), $dates)
                ->selectRaw('referral_user_id, DATE(created_at) as cfa_date, COUNT(*) as cfa_count')
                ->groupBy('referral_user_id', 'cfa_date')
                ->get();

            foreach ($rows as $row) {
                $key = $row->referral_user_id.'_'.$row->cfa_date;
                $cfaMap[$key] = (int) $row->cfa_count;
            }
        }

        return view('admin.attendance.index', [
            'reports'       => $reports,
            'searchQuery'   => $search,
            'coordinators'  => $coordinators,
            'coordinatorId' => $coordinatorId,
            'cfaMap'        => $cfaMap,
        ]);
    }

    public function downloadAttachment(FieldCoordinatorAttendanceReport $attendanceReport): StreamedResponse
    {
        abort_if(! $attendanceReport->attachment_path, 404);
        abort_unless(Storage::exists($attendanceReport->attachment_path), 404);

        return Storage::download(
            $attendanceReport->attachment_path,
            $attendanceReport->attachment_original_name ?: basename($attendanceReport->attachment_path)
        );
    }
}
