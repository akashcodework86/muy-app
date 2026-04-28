<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->isFieldCoordinator($user), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blocks = $districtId > 0
            ? DistrictBlock::orderedNamesForDistrict($districtId)
            : config('cfa.blocks_by_district.'.($user->district?->name ?? ''), []);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.index', [
                'reports' => collect(),
                'user' => $user,
                'blocks' => $blocks,
                'migrationMissing' => true,
            ]);
        }

        $reports = FieldCoordinatorAttendanceReport::query()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->with('district')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // CFA count per visit date for this coordinator
        $visitDates = $reports->pluck('visit_date')
            ->filter()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        $cfaByDate = [];
        if ($visitDates !== []) {
            $cfaByDate = CfaSubmission::query()
                ->where('referral_user_id', (int) $user->id)
                ->whereIn(DB::raw('DATE(created_at)'), $visitDates)
                ->selectRaw('DATE(created_at) as cfa_date, COUNT(*) as cfa_count')
                ->groupBy('cfa_date')
                ->pluck('cfa_count', 'cfa_date')
                ->all();
        }

        return view('staff.attendance.index', [
            'reports'    => $reports,
            'user'       => $user,
            'blocks'     => $blocks,
            'cfaByDate'  => $cfaByDate,
        ]);
    }

    public function view(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.view', [
                'reports'        => collect(),
                'user'           => $user,
                'cfaByDate'      => [],
                'migrationMissing' => true,
            ]);
        }

        $query = FieldCoordinatorAttendanceReport::query()->with('district');
        if ($this->isFieldCoordinator($user)) {
            $query->where('field_coordinator_user_id', (int) $user->id);
        } else {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $query->where('block', $request->query('block'));
        }

        $reports = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $visitDates = $reports->pluck('visit_date')
            ->filter()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()->values()->all();

        $cfaByDate = [];
        if ($visitDates !== []) {
            $cfaByDateQuery = CfaSubmission::query()
                ->whereIn(DB::raw('DATE(created_at)'), $visitDates)
                ->selectRaw('DATE(created_at) as cfa_date, COUNT(*) as cfa_count');

            if ($this->isFieldCoordinator($user)) {
                $cfaByDateQuery->where('referral_user_id', (int) $user->id);
            } else {
                $cfaByDateQuery->where('district_id', (int) ($user->district_id ?: 0));
            }

            $cfaByDate = $cfaByDateQuery
                ->groupBy('cfa_date')
                ->pluck('cfa_count', 'cfa_date')
                ->all();
        }

        // Summary totals for visible page
        $totalVillages    = $reports->sum('villages_visited_total');
        $totalParticipants = $reports->sum('participants_total');
        $totalCfas        = $reports->sum('cfas_filled_total');
        $totalOutreach    = $reports->sum('outreach_programmes_total');

        // All blocks submitted by this staff for filter dropdown
        $blockOptionsQuery = FieldCoordinatorAttendanceReport::query();
        if ($this->isFieldCoordinator($user)) {
            $blockOptionsQuery->where('field_coordinator_user_id', (int) $user->id);
        } else {
            $blockOptionsQuery->where('district_id', (int) ($user->district_id ?: 0));
        }
        $blockOptions = $blockOptionsQuery
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('staff.attendance.view', [
            'reports'          => $reports,
            'user'             => $user,
            'cfaByDate'        => $cfaByDate,
            'totalVillages'    => $totalVillages,
            'totalParticipants'=> $totalParticipants,
            'totalCfas'        => $totalCfas,
            'totalOutreach'    => $totalOutreach,
            'blockOptions'     => $blockOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->isFieldCoordinator($user), 403);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return redirect()
                ->route('staff.attendance.index')
                ->withErrors(['attendance' => 'Attendance table is missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'visit_date' => ['required', 'date'],
            'entry_date' => ['required', 'date'],
            'area' => ['nullable', 'string', 'max:191'],
            'block' => ['nullable', 'string', 'max:191'],
            'villages_visited_total' => ['required', 'integer', 'min:0'],
            'villages_covered' => ['nullable', 'string', 'max:4000'],
            'participants_total' => ['required', 'integer', 'min:0'],
            'cfas_filled_total' => ['required', 'integer', 'min:0'],
            'outreach_programmes_total' => ['required', 'integer', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('attendance-attachments');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
        }

        $villages = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['villages_covered'] ?? '')) ?: [])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => (int) $user->id,
            'field_coordinator_name' => (string) $user->name,
            'visit_date' => $validated['visit_date'],
            'entry_date' => $validated['entry_date'],
            'area' => $validated['area'] ?? null,
            'block' => $validated['block'] ?? null,
            'district_id' => (int) ($user->district_id ?: 0) ?: null,
            'villages_visited_total' => (int) $validated['villages_visited_total'],
            'villages_covered' => $villages,
            'participants_total' => (int) $validated['participants_total'],
            'cfas_filled_total' => (int) $validated['cfas_filled_total'],
            'outreach_programmes_total' => (int) $validated['outreach_programmes_total'],
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size_bytes' => $attachmentSize,
        ]);

        return redirect()->route('staff.attendance.index')->with('status', 'Attendance report submitted.');
    }

    public function downloadAttachment(FieldCoordinatorAttendanceReport $attendanceReport, Request $request): StreamedResponse
    {
        $user = $request->user()->load('designationRecord');
        $isOwn = (int) $attendanceReport->field_coordinator_user_id === (int) $user->id;
        $isDistrictViewer = ! $this->isFieldCoordinator($user)
            && (int) ($attendanceReport->district_id ?: 0) > 0
            && (int) ($attendanceReport->district_id ?: 0) === (int) ($user->district_id ?: 0);
        abort_unless($isOwn || $isDistrictViewer, 403);
        abort_if(! $attendanceReport->attachment_path, 404);
        abort_unless(Storage::exists($attendanceReport->attachment_path), 404);

        return Storage::download(
            $attendanceReport->attachment_path,
            $attendanceReport->attachment_original_name ?: basename($attendanceReport->attachment_path)
        );
    }

    private function isFieldCoordinator(User $user): bool
    {
        $designation = strtolower(trim((string) ($user->designationRecord?->name ?? '')));

        return str_contains($designation, 'field coordinator')
            || str_contains($designation, 'field co-ordinator');
    }
}
