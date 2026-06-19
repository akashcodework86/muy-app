<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\User;
use App\Services\FieldCoordinatorVisitReportExport;
use App\Services\FieldVisitAttendanceSheetService;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceAdminController extends Controller
{
    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
    ) {}

    public function index(Request $request): View
    {
        $coordinators = User::query()
            ->where('role', 'district_staff')
            ->where('is_active', true)
            ->whereHas('designationRecord', fn ($d) => $d->where('name', 'like', '%field coordinator%'))
            ->with('district:id,name')
            ->select('id', 'name', 'district_id')
            ->orderBy('name')
            ->get();

        $query = $this->filteredQuery($request);

        $search = trim((string) $request->query('q', ''));
        $coordinatorId = (int) $request->query('coordinator_id', 0);

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

    public function export(Request $request, FieldCoordinatorVisitReportExport $exporter): BinaryFileResponse
    {
        $reports = $this->filteredQuery($request)
            ->orderBy('field_coordinator_name')
            ->orderBy('visit_date')
            ->get();

        $filename = 'field-coordinator-visit-report-'.now()->format('Y-m-d_His').'.xlsx';

        return $exporter->download($reports, $this->filterSummary($request), $filename);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = FieldCoordinatorAttendanceReport::query()
            ->with([
                'coordinator:id,name,designation_id,district_id',
                'coordinator.designationRecord:id,name',
                'coordinator.district:id,name',
                'district:id,name',
                'gramPanchayat:id,name',
            ]);

        if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $query->submitted();
        }

        // Exclude block-workshop records — they have their own dedicated admin page.
        if (FieldCoordinatorAttendanceReport::supportsRecordType()) {
            $query->fieldVisits();
        }

        // Only show submissions from staff whose designation is Field Coordinator.
        $query->whereHas('coordinator.designationRecord', fn ($d) => $d->where('name', 'like', '%field coordinator%'));

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

        return $query;
    }

    private function filterSummary(Request $request): string
    {
        $parts = [];

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $parts[] = 'Search: "'.$search.'"';
        }

        $coordinatorId = (int) $request->query('coordinator_id', 0);
        if ($coordinatorId > 0) {
            $name = User::query()->whereKey($coordinatorId)->value('name');
            if ($name) {
                $parts[] = 'Coordinator: '.$name;
            }
        }

        if ($request->filled('from')) {
            $parts[] = 'From: '.(string) $request->query('from');
        }
        if ($request->filled('to')) {
            $parts[] = 'To: '.(string) $request->query('to');
        }

        return $parts === [] ? 'All records' : implode('  |  ', $parts);
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

    public function downloadAttendanceSheet(FieldCoordinatorAttendanceReport $attendanceReport): StreamedResponse
    {
        abort_unless($attendanceReport->hasAttendanceSheet(), 404);

        return $this->attendanceSheetService->downloadStored(
            (string) $attendanceReport->attendance_sheet_path,
            (string) ($attendanceReport->attendance_sheet_original_name ?: 'attendance-sheet.xlsx'),
        );
    }
}
