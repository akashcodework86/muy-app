<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\GramPanchayat;
use App\Models\User;
use App\Services\FieldVisitAttendanceSheetService;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blockRows = $districtId > 0
            ? DistrictBlock::query()
                ->where('district_id', $districtId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.index', [
                'reports' => collect(),
                'user' => $user,
                'blockRows' => $blockRows,
                'gramPanchayatsEnabled' => false,
                'migrationMissing' => true,
            ]);
        }

        $reports = FieldCoordinatorAttendanceReport::query()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->with(['district', 'gramPanchayat'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('staff.attendance.index', [
            'reports' => $reports,
            'user' => $user,
            'blockRows' => $blockRows,
            'gramPanchayatsEnabled' => Schema::hasTable('gram_panchayats'),
            'migrationMissing' => false,
        ]);
    }

    public function downloadAttendanceSheetTemplate(Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmitFieldVisit($user), 403);

        $validated = $request->validate([
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'participants_male_count' => ['required', 'integer', 'min:0'],
            'participants_female_count' => ['required', 'integer', 'min:0'],
        ]);

        $districtId = (int) ($user->district_id ?: 0);
        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 403);

        $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
        abort_unless((int) $gramPanchayat->district_block_id === (int) $block->id, 403);

        $male = (int) $validated['participants_male_count'];
        $female = (int) $validated['participants_female_count'];
        $total = $male + $female;

        return $this->attendanceSheetService->streamTemplateDownload(
            $total,
            (string) ($user->district?->name ?? ''),
            (string) $block->name,
            (string) $gramPanchayat->name,
        );
    }

    public function downloadAttendanceSheetTemplateForReport(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        $attendanceReport->load(['district', 'gramPanchayat']);

        $male = (int) $attendanceReport->participants_male_count;
        $female = (int) $attendanceReport->participants_female_count;
        $total = (int) $attendanceReport->participants_total;
        if ($total <= 0) {
            $total = $male + $female;
        }
        abort_if($total <= 0, 422, 'This submission has no participants — template is not required.');

        return $this->attendanceSheetService->streamTemplateDownload(
            $total,
            (string) ($attendanceReport->district?->name ?? $user->district?->name ?? ''),
            (string) ($attendanceReport->block ?? ''),
            $attendanceReport->attendanceSheetGramPanchayatLabel(),
        );
    }

    public function gramPanchayats(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless(Schema::hasTable('gram_panchayats'), 404);

        $blockId = (int) $request->query('district_block_id', 0);
        abort_if($blockId <= 0, 422);

        $block = DistrictBlock::query()->findOrFail($blockId);
        abort_unless((int) $block->district_id === (int) ($user->district_id ?: 0), 403);

        $search = trim((string) $request->query('q', ''));

        $query = GramPanchayat::query()
            ->where('district_block_id', $blockId)
            ->orderBy('name');

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where('name', 'like', $like);
        }

        $items = $query->limit(100)->get(['id', 'name']);

        return response()->json([
            'items' => $items->map(fn (GramPanchayat $gp) => [
                'id' => $gp->id,
                'name' => $gp->name,
            ])->values(),
        ]);
    }

    public function view(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.view', [
                'reports' => collect(),
                'user' => $user,
                'blockOptions' => [],
                'migrationMissing' => true,
            ]);
        }

        $query = FieldCoordinatorAttendanceReport::query()
            ->with(['district', 'gramPanchayat'])
            ->where('district_id', (int) ($user->district_id ?: 0));

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

        $blockOptionsQuery = FieldCoordinatorAttendanceReport::query()
            ->where('district_id', (int) ($user->district_id ?: 0));
        $blockOptions = $blockOptionsQuery
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        return view('staff.attendance.view', [
            'reports' => $reports,
            'user' => $user,
            'blockOptions' => $blockOptions,
            'migrationMissing' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return redirect()
                ->route('staff.attendance.index')
                ->withErrors(['attendance' => 'Attendance table is missing. Please run migrations first.']);
        }

        $rules = [
            'visit_date' => ['required', 'date'],
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'area' => ['required', 'string', 'max:191'],
            'participants_male_count' => ['required', 'integer', 'min:0'],
            'participants_female_count' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'visit_media' => ['required', 'array', 'min:1', 'max:15'],
            'visit_media.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attendance_sheet' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ];

        if (! Schema::hasTable('gram_panchayats')) {
            unset($rules['gram_panchayat_id']);
            $rules['gram_panchayat_id'] = ['nullable'];
        }

        $validated = $request->validate($rules);
        $uploadErrors = [];
        foreach ((array) $request->file('visit_media', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $uploadErrors['visit_media.'.$index] = $this->describeFailedUpload($file);
            }
        }
        if ($uploadErrors !== []) {
            return back()->withErrors($uploadErrors)->withInput();
        }

        $districtId = (int) ($user->district_id ?: 0);
        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 422);

        $gramPanchayat = null;
        if (Schema::hasTable('gram_panchayats')) {
            $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
            abort_unless((int) $gramPanchayat->district_block_id === (int) $block->id, 422);
        }

        $mediaItems = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
        if ($mediaItems === []) {
            return back()
                ->withErrors(['visit_media' => 'Upload at least one photo.'])
                ->withInput();
        }

        $male = (int) $validated['participants_male_count'];
        $female = (int) $validated['participants_female_count'];
        $participantsTotal = $male + $female;

        $sheetPayload = [];
        $sheetFile = $request->file('attendance_sheet');
        if ($sheetFile instanceof UploadedFile) {
            if ($participantsTotal <= 0) {
                return back()
                    ->withErrors(['attendance_sheet' => 'Set participant counts before uploading an attendance sheet.'])
                    ->withInput();
            }

            $this->attendanceSheetService->assertValidUpload(
                $sheetFile,
                $participantsTotal,
                $male,
                $female,
                (string) ($user->district?->name ?? ''),
                (string) $block->name,
                (string) ($gramPanchayat?->name ?? ''),
            );

            $sheetPayload = $this->attendanceSheetService->storeUploadedFile($sheetFile);
        }

        FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => (int) $user->id,
            'field_coordinator_name' => (string) $user->name,
            'visit_date' => $validated['visit_date'],
            'entry_date' => now()->toDateString(),
            'block' => (string) $block->name,
            'district_block_id' => (int) $block->id,
            'gram_panchayat_id' => $gramPanchayat?->id,
            'area' => $validated['area'],
            'remark' => $validated['remark'] ?? null,
            'visit_media_json' => $mediaItems,
            'district_id' => $districtId > 0 ? $districtId : null,
            'villages_visited_total' => 0,
            'villages_covered' => null,
            'participants_male_count' => $male,
            'participants_female_count' => $female,
            'participants_total' => $participantsTotal,
            'cfas_filled_total' => 0,
            'outreach_programmes_total' => 0,
            ...$sheetPayload,
        ]);

        $status = 'Field visit report submitted.';
        if ($participantsTotal > 0 && $sheetPayload === []) {
            $status .= ' You can upload the attendance Excel sheet later from My submissions.';
        }

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', $status);
    }

    public function uploadAttendanceSheet(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        $participantsTotal = (int) $attendanceReport->participants_total;
        abort_if($participantsTotal <= 0, 422);

        $validated = $request->validate([
            'attendance_sheet' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $sheetFile = $request->file('attendance_sheet');
        abort_if(! $sheetFile instanceof UploadedFile, 422);

        $attendanceReport->load(['district', 'gramPanchayat']);

        $this->attendanceSheetService->assertValidUpload(
            $sheetFile,
            $participantsTotal,
            (int) $attendanceReport->participants_male_count,
            (int) $attendanceReport->participants_female_count,
            (string) ($attendanceReport->district?->name ?? ''),
            (string) ($attendanceReport->block ?? ''),
            $attendanceReport->attendanceSheetGramPanchayatLabel(),
        );

        if ($attendanceReport->attendance_sheet_path) {
            Storage::delete($attendanceReport->attendance_sheet_path);
        }

        $attendanceReport->update($this->attendanceSheetService->storeUploadedFile($sheetFile));

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', 'Attendance sheet uploaded.');
    }

    public function edit(FieldCoordinatorAttendanceReport $attendanceReport, Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blockRows = $districtId > 0
            ? DistrictBlock::query()
                ->where('district_id', $districtId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $attendanceReport->load(['district', 'gramPanchayat']);

        return view('staff.attendance.edit', [
            'report' => $attendanceReport,
            'user' => $user,
            'blockRows' => $blockRows,
            'gramPanchayatsEnabled' => Schema::hasTable('gram_panchayats'),
        ]);
    }

    public function update(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        $rules = [
            'visit_date' => ['required', 'date'],
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'area' => ['required', 'string', 'max:191'],
            'participants_male_count' => ['required', 'integer', 'min:0'],
            'participants_female_count' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];

        if (! Schema::hasTable('gram_panchayats')) {
            unset($rules['gram_panchayat_id']);
            $rules['gram_panchayat_id'] = ['nullable'];
        }

        $validated = $request->validate($rules);

        $districtId = (int) ($user->district_id ?: 0);
        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 422);

        $gramPanchayat = null;
        if (Schema::hasTable('gram_panchayats')) {
            $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
            abort_unless((int) $gramPanchayat->district_block_id === (int) $block->id, 422);
        }

        $male = (int) $validated['participants_male_count'];
        $female = (int) $validated['participants_female_count'];
        $participantsTotal = $male + $female;

        $attendanceReport->load(['district', 'gramPanchayat']);

        $locationOrCountsChanged = (int) $attendanceReport->participants_male_count !== $male
            || (int) $attendanceReport->participants_female_count !== $female
            || (int) $attendanceReport->participants_total !== $participantsTotal
            || (int) $attendanceReport->district_block_id !== (int) $block->id
            || (int) $attendanceReport->gram_panchayat_id !== (int) ($gramPanchayat?->id ?: 0)
            || (string) $attendanceReport->area !== (string) $validated['area']
            || (string) $attendanceReport->block !== (string) $block->name;

        if ($locationOrCountsChanged && $attendanceReport->hasAttendanceSheet()) {
            $sheetPath = (string) $attendanceReport->attendance_sheet_path;
            if ($sheetPath !== '' && Storage::exists($sheetPath)) {
                Storage::delete($sheetPath);
            }
            $attendanceReport->attendance_sheet_path = null;
            $attendanceReport->attendance_sheet_original_name = null;
            $attendanceReport->attendance_sheet_mime = null;
            $attendanceReport->attendance_sheet_size_bytes = null;
        }

        $attendanceReport->update([
            'visit_date' => $validated['visit_date'],
            'block' => (string) $block->name,
            'district_block_id' => (int) $block->id,
            'gram_panchayat_id' => $gramPanchayat?->id,
            'area' => $validated['area'],
            'remark' => $validated['remark'] ?? null,
            'participants_male_count' => $male,
            'participants_female_count' => $female,
            'participants_total' => $participantsTotal,
            'district_id' => $districtId > 0 ? $districtId : null,
        ]);

        $status = 'Visit updated.';
        if ($locationOrCountsChanged && $participantsTotal > 0) {
            $status .= ' Download a new attendance template and upload the sheet again.';
        }

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', $status);
    }

    public function destroy(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        $sheetPath = (string) ($attendanceReport->attendance_sheet_path ?? '');
        if ($sheetPath !== '' && Storage::exists($sheetPath)) {
            Storage::delete($sheetPath);
        }

        $this->mediaStorage->deleteAllForReport($attendanceReport);
        $attendanceReport->delete();

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', 'Visit deleted.');
    }

    public function downloadAttendanceSheet(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $user = $request->user()->load('district');
        abort_unless($this->canViewFieldVisit($user, $attendanceReport), 403);
        abort_unless($attendanceReport->hasAttendanceSheet(), 404);

        return $this->attendanceSheetService->downloadStored(
            (string) $attendanceReport->attendance_sheet_path,
            (string) ($attendanceReport->attendance_sheet_original_name ?: 'attendance-sheet.xlsx'),
        );
    }

    public function downloadAttachment(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $user = $request->user()->load('district');
        abort_unless($this->canViewFieldVisit($user, $attendanceReport), 403);

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

    private function canSubmitFieldVisit(User $user): bool
    {
        return $user->role === 'district_staff'
            && (int) ($user->district_id ?: 0) > 0;
    }

    private function canViewFieldVisit(User $user, FieldCoordinatorAttendanceReport $report): bool
    {
        if ($user->role !== 'district_staff') {
            return false;
        }

        $isOwn = (int) $report->field_coordinator_user_id === (int) $user->id;
        $districtId = (int) ($user->district_id ?: 0);
        $isSameDistrict = $districtId > 0
            && (int) ($report->district_id ?: 0) === $districtId;

        return $isOwn || $isSameDistrict;
    }
}
