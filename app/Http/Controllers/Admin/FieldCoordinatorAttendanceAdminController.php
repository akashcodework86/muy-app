<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\User;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceAdminController extends Controller
{
    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
    ) {}

    public function index(Request $request): View
    {
        $coordinators = User::query()
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->with('district:id,name')
            ->select('id', 'name', 'district_id')
            ->orderBy('name')
            ->get();

        $query = FieldCoordinatorAttendanceReport::query()
            ->with(['coordinator:id,name', 'district:id,name', 'gramPanchayat:id,name']);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('field_coordinator_name', 'like', $like)
                    ->orWhere('block', 'like', $like)
                    ->orWhere('remark', 'like', $like)
                    ->orWhereHas('gramPanchayat', fn ($gp) => $gp->where('name', 'like', $like));
            });
        }

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

        return view('admin.attendance.index', [
            'reports' => $reports,
            'searchQuery' => $search,
            'coordinators' => $coordinators,
            'coordinatorId' => $coordinatorId,
        ]);
    }

    public function downloadAttachment(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $index = $request->query('index');
        if ($index !== null && $index !== '') {
            return $this->mediaStorage->download(
                $attendanceReport,
                (int) $index,
                $request->boolean('inline'),
            );
        }

        return $this->mediaStorage->legacyDownload($attendanceReport);
    }
}
