<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\DistrictBlock;
use App\Models\FieldCoordinatorAttendanceReport;
use App\Models\GramPanchayat;
use App\Models\User;
use App\Services\BlockWorkshopParticipantRowsService;
use App\Services\FieldVisitAttendanceSheetService;
use App\Services\FieldVisitMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldCoordinatorAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
        private readonly BlockWorkshopParticipantRowsService $participantRowsService,
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

        $draftWorkflow = FieldCoordinatorAttendanceReport::supportsDraftWorkflow();

        if (! Schema::hasTable('field_coordinator_attendance_reports')) {
            return view('staff.attendance.index', [
                'reports' => collect(),
                'user' => $user,
                'blockRows' => $blockRows,
                'gramPanchayatsEnabled' => false,
                'migrationMissing' => true,
                'draftWorkflow' => false,
                'activeDraft' => null,
            ]);
        }

        $editId = (int) $request->query('edit', 0);
        $editingSubmitted = false;
        $activeDraft = null;

        if ($draftWorkflow && $editId > 0) {
            $activeDraft = FieldCoordinatorAttendanceReport::query()
                ->submitted()
                ->where('field_coordinator_user_id', (int) $user->id)
                ->whereKey($editId)
                ->with(['district', 'gramPanchayat', 'districtBlock'])
                ->firstOrFail();
            $editingSubmitted = true;
        }

        if ($activeDraft === null && $draftWorkflow) {
            $draftId = (int) $request->query('draft', 0);
            if ($draftId > 0) {
                $activeDraft = FieldCoordinatorAttendanceReport::query()
                    ->draft()
                    ->where('field_coordinator_user_id', (int) $user->id)
                    ->whereKey($draftId)
                    ->with(['district', 'gramPanchayat', 'districtBlock'])
                    ->first();
            }
            if ($activeDraft === null) {
                $activeDraft = FieldCoordinatorAttendanceReport::query()
                    ->draft()
                    ->where('field_coordinator_user_id', (int) $user->id)
                    ->with(['district', 'gramPanchayat', 'districtBlock'])
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }

        $reportsQuery = FieldCoordinatorAttendanceReport::query()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->with(['district', 'gramPanchayat']);

        if ($draftWorkflow) {
            $reportsQuery->submitted();
        }

        if (FieldCoordinatorAttendanceReport::supportsRecordType()) {
            $reportsQuery->fieldVisits();
        }

        $reports = $reportsQuery
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
            'draftWorkflow' => $draftWorkflow,
            'activeDraft' => $activeDraft,
            'editingSubmitted' => $editingSubmitted,
        ]);
    }

    public function createDraft(Request $request): JsonResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless(FieldCoordinatorAttendanceReport::supportsDraftWorkflow(), 404);

        $existing = FieldCoordinatorAttendanceReport::query()
            ->draft()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->orderByDesc('updated_at')
            ->first();

        if ($existing) {
            return response()->json([
                'id' => $existing->id,
                'redirect_url' => route('staff.attendance.index', ['draft' => $existing->id]),
            ]);
        }

        $districtId = (int) ($user->district_id ?: 0);

        $draft = FieldCoordinatorAttendanceReport::query()->create([
            'field_coordinator_user_id' => (int) $user->id,
            'field_coordinator_name' => (string) $user->name,
            'visit_date' => now()->toDateString(),
            'entry_date' => now()->toDateString(),
            'district_id' => $districtId > 0 ? $districtId : null,
            'status' => FieldCoordinatorAttendanceReport::STATUS_DRAFT,
            'record_type' => FieldCoordinatorAttendanceReport::TYPE_BLOCK_WORKSHOP,
            'participants_male_count' => 0,
            'participants_female_count' => 0,
            'participants_total' => 0,
            'participants_json' => [],
            'villages_visited_total' => 0,
            'cfas_filled_total' => 0,
            'outreach_programmes_total' => 0,
        ]);

        return response()->json([
            'id' => $draft->id,
            'redirect_url' => route('staff.attendance.index', ['draft' => $draft->id]),
        ], 201);
    }

    public function updateDraftMeta(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): JsonResponse {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmitFieldVisit($user), 403);
        $this->assertOwnEditable($user, $attendanceReport);

        $rules = [
            'visit_date' => ['nullable', 'date'],
            'district_block_id' => ['nullable', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['nullable', 'integer', 'exists:gram_panchayats,id'],
            'area' => ['nullable', 'string', 'max:191'],
            'participants_male_count' => ['nullable', 'integer', 'min:0', 'max:'.BlockWorkshopParticipantRowsService::MAX_ROWS],
            'participants_female_count' => ['nullable', 'integer', 'min:0', 'max:'.BlockWorkshopParticipantRowsService::MAX_ROWS],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];

        if (! Schema::hasTable('gram_panchayats')) {
            $rules['gram_panchayat_id'] = ['nullable'];
        }

        $validated = $request->validate($rules);

        $districtId = (int) ($user->district_id ?: 0);
        $male = (int) ($validated['participants_male_count'] ?? $attendanceReport->participants_male_count);
        $female = (int) ($validated['participants_female_count'] ?? $attendanceReport->participants_female_count);
        $total = $male + $female;

        $block = null;
        $gramPanchayat = null;
        if (! empty($validated['district_block_id'])) {
            $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
            abort_unless((int) $block->district_id === $districtId, 403);
        }
        if (! empty($validated['gram_panchayat_id']) && Schema::hasTable('gram_panchayats')) {
            $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
            abort_if($block && (int) $gramPanchayat->district_block_id !== (int) $block->id, 422);
        }

        $districtName = (string) ($user->district?->name ?? '');
        $blockName = (string) ($block?->name ?? $attendanceReport->block ?? '');
        $gpId = $gramPanchayat?->id ?? $attendanceReport->gram_panchayat_id;
        $gpName = (string) ($gramPanchayat?->name ?? $attendanceReport->gramPanchayat?->name ?? '');

        $rows = $this->participantRowsService->syncRowCount(
            $attendanceReport->participantRows(),
            $male,
            $female,
            $districtName,
            $blockName,
            $gpId ? (int) $gpId : null,
            $gpName !== '' ? $gpName : null,
        );

        $payload = [
            'participants_male_count' => $male,
            'participants_female_count' => $female,
            'participants_total' => $total,
            'participants_json' => $rows,
        ];

        if (array_key_exists('visit_date', $validated) && $validated['visit_date'] !== null) {
            $payload['visit_date'] = $validated['visit_date'];
        }
        if (array_key_exists('area', $validated)) {
            $payload['area'] = $validated['area'];
        }
        if (array_key_exists('remark', $validated)) {
            $payload['remark'] = $validated['remark'];
        }
        if ($block) {
            $payload['district_block_id'] = (int) $block->id;
            $payload['block'] = (string) $block->name;
        }
        if ($gramPanchayat) {
            $payload['gram_panchayat_id'] = (int) $gramPanchayat->id;
        }

        $attendanceReport->update($payload);
        $attendanceReport->load(['district', 'gramPanchayat', 'districtBlock']);

        return response()->json([
            'ok' => true,
            'participants' => $attendanceReport->participantRows(),
            'participants_total' => $total,
        ]);
    }

    public function saveParticipants(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): JsonResponse {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmitFieldVisit($user), 403);
        $this->assertOwnEditable($user, $attendanceReport);

        $validated = $request->validate([
            'participants' => ['nullable', 'array', 'max:'.BlockWorkshopParticipantRowsService::MAX_ROWS],
            'participants.*.name' => ['nullable', 'string', 'max:191'],
            'participants.*.mobile' => ['nullable', 'string', 'max:20'],
            'participants.*.gender' => ['nullable', 'string', 'max:1'],
            'participants.*.gram_panchayat_id' => ['nullable', 'integer'],
            'participants.*.gram_panchayat_name' => ['nullable', 'string', 'max:191'],
            'participants.*.district_name' => ['nullable', 'string', 'max:191'],
            'participants.*.block_name' => ['nullable', 'string', 'max:191'],
        ]);

        $male = (int) $attendanceReport->participants_male_count;
        $female = (int) $attendanceReport->participants_female_count;
        $expected = $male + $female;

        $districtName = (string) ($user->district?->name ?? '');
        $blockName = (string) ($attendanceReport->block ?? '');
        $gpId = $attendanceReport->gram_panchayat_id ? (int) $attendanceReport->gram_panchayat_id : null;
        $gpName = (string) ($attendanceReport->gramPanchayat?->name ?? '');

        $rows = $this->participantRowsService->sanitizeIncoming(
            $validated['participants'] ?? [],
            $expected,
        );

        if ($rows === [] && $expected > 0) {
            $rows = $this->participantRowsService->syncRowCount(
                null,
                $male,
                $female,
                $districtName,
                $blockName,
                $gpId,
                $gpName !== '' ? $gpName : null,
            );
        }

        foreach ($rows as $i => $row) {
            if (($row['district_name'] ?? '') === '') {
                $rows[$i]['district_name'] = $districtName;
            }
            if (($row['block_name'] ?? '') === '') {
                $rows[$i]['block_name'] = $blockName;
            }
        }

        $attendanceReport->update(['participants_json' => $rows]);

        return response()->json([
            'ok' => true,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function redirectSubmitPage(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        if ($attendanceReport->isSubmitted()) {
            return redirect()
                ->route('staff.attendance.show', $attendanceReport)
                ->with('status', 'This workshop was already submitted.');
        }

        return redirect()
            ->route('staff.attendance.index', ['draft' => $attendanceReport->id])
            ->with('status', 'Use the form below to finish and submit your draft workshop.');
    }

    public function submitDraft(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        $this->assertOwnDraft($user, $attendanceReport);

        $maxPhotos = FieldVisitMediaStorage::MAX_PHOTOS_PER_REPORT;
        $existingMedia = $attendanceReport->visitMediaItems();
        $remainingSlots = max(0, $maxPhotos - count($existingMedia));

        $rules = [
            'visit_date' => ['required', 'date'],
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'area' => ['required', 'string', 'max:191'],
            'participants_male_count' => ['required', 'integer', 'min:0'],
            'participants_female_count' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'visit_media' => ['nullable', 'array', 'max:'.$remainingSlots],
            'visit_media.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

        $newMedia = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
        $mediaItems = $this->mediaStorage->mergeOntoReport($attendanceReport, $newMedia);
        if ($mediaItems === []) {
            return back()
                ->withErrors(['visit_media' => 'Upload at least one workshop photo (select files above — they save automatically).'])
                ->withInput();
        }

        $male = (int) $validated['participants_male_count'];
        $female = (int) $validated['participants_female_count'];
        $participantsTotal = $male + $female;

        $districtName = (string) ($user->district?->name ?? '');
        $gpName = (string) ($gramPanchayat?->name ?? '');
        $rows = $this->participantRowsService->syncRowCount(
            $attendanceReport->participantRows(),
            $male,
            $female,
            $districtName,
            (string) $block->name,
            $gramPanchayat?->id,
            $gpName !== '' ? $gpName : null,
        );

        if ($request->has('participants') && is_array($request->input('participants'))) {
            $rows = $this->participantRowsService->sanitizeIncoming(
                $request->input('participants'),
                $participantsTotal,
            );
        }

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
                $districtName,
                (string) $block->name,
                $gpName,
            );

            $sheetPayload = $this->attendanceSheetService->storeUploadedFile($sheetFile);
        }

        $attendanceReport->update([
            'visit_date' => $validated['visit_date'],
            'entry_date' => now()->toDateString(),
            'block' => (string) $block->name,
            'district_block_id' => (int) $block->id,
            'gram_panchayat_id' => $gramPanchayat?->id,
            'area' => $validated['area'],
            'remark' => $validated['remark'] ?? null,
            'visit_media_json' => $mediaItems,
            'district_id' => $districtId > 0 ? $districtId : null,
            'participants_male_count' => $male,
            'participants_female_count' => $female,
            'participants_total' => $participantsTotal,
            'participants_json' => $rows,
            'status' => FieldCoordinatorAttendanceReport::STATUS_SUBMITTED,
            ...$sheetPayload,
        ]);

        $status = 'Block level workshop submitted.';

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', $status)
            ->setStatusCode(303);
    }

    public function uploadPhotos(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        $this->assertOwnEditable($user, $attendanceReport);

        $current = count($attendanceReport->visitMediaItems());
        $remaining = FieldVisitMediaStorage::MAX_PHOTOS_PER_REPORT - $current;
        abort_if($remaining <= 0, 422, 'Maximum '.FieldVisitMediaStorage::MAX_PHOTOS_PER_REPORT.' photos already uploaded.');

        $validated = $request->validate([
            'visit_media' => ['required', 'array', 'min:1', 'max:'.$remaining],
            'visit_media.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $uploadErrors = [];
        foreach ((array) $request->file('visit_media', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $uploadErrors['visit_media.'.$index] = $this->describeFailedUpload($file);
            }
        }
        if ($uploadErrors !== []) {
            return response()->json(['message' => implode(' ', $uploadErrors)], 422);
        }

        $newItems = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
        if ($newItems === []) {
            return response()->json(['message' => 'No valid photos were uploaded.'], 422);
        }

        $merged = $this->mediaStorage->mergeOntoReport($attendanceReport, $newItems);
        $attendanceReport->update(['visit_media_json' => $merged]);

        return response()->json([
            'ok' => true,
            'count' => count($merged),
            'items' => $this->photoItemsForJson($attendanceReport->fresh()),
        ]);
    }

    public function deletePhoto(
        FieldCoordinatorAttendanceReport $attendanceReport,
        int $photoIndex,
        Request $request,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($this->canSubmitFieldVisit($user), 403);
        $this->assertOwnEditable($user, $attendanceReport);

        $items = $this->mediaStorage->removeAt($attendanceReport, $photoIndex);
        $attendanceReport->update(['visit_media_json' => $items]);

        return response()->json([
            'ok' => true,
            'count' => count($items),
            'items' => $this->photoItemsForJson($attendanceReport->fresh()),
        ]);
    }

    /**
     * @return list<array{index: int, url: string, name: string}>
     */
    private function photoItemsForJson(FieldCoordinatorAttendanceReport $report): array
    {
        return collect($report->visitMediaItems())
            ->values()
            ->map(fn (array $item, int $index) => [
                'index' => $index,
                'url' => route('staff.attendance.attachment', [
                    'attendanceReport' => $report,
                    'index' => $index,
                    'inline' => 1,
                ]),
                'name' => (string) ($item['original_name'] ?? 'photo'),
            ])
            ->all();
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
                'totalWorkshops' => 0,
                'totalMale' => 0,
                'totalFemale' => 0,
                'totalParticipants' => 0,
            ]);
        }

        $districtId = (int) ($user->district_id ?: 0);

        $baseQuery = function () use ($districtId): \Illuminate\Database\Eloquent\Builder {
            $q = FieldCoordinatorAttendanceReport::query()
                ->where('district_id', $districtId);

            if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
                $q->submitted();
            }

            if (FieldCoordinatorAttendanceReport::supportsRecordType()) {
                $q->fieldVisits();
            }

            return $q;
        };

        $query = $baseQuery()
            ->with(['district', 'gramPanchayat', 'coordinator:id,name']);

        if ($request->filled('from')) {
            $query->whereDate('visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $query->where('block', $request->query('block'));
        }
        if ($request->filled('coordinator_id')) {
            $query->where('field_coordinator_user_id', (int) $request->query('coordinator_id'));
        }

        $reports = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $statsQuery = $baseQuery();
        if ($request->filled('from')) {
            $statsQuery->whereDate('visit_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $statsQuery->whereDate('visit_date', '<=', $request->query('to'));
        }
        if ($request->filled('block')) {
            $statsQuery->where('block', $request->query('block'));
        }

        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_workshops,
            COALESCE(SUM(participants_male_count), 0) as total_male,
            COALESCE(SUM(participants_female_count), 0) as total_female,
            COALESCE(SUM(participants_total), 0) as total_participants
        ')->first();

        $blockOptions = $baseQuery()
            ->whereNotNull('block')
            ->where('block', '!=', '')
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();

        $coordinatorOptions = FieldCoordinatorAttendanceReport::query()
            ->where('district_id', $districtId)
            ->with(['coordinator:id,name'])
            ->when(FieldCoordinatorAttendanceReport::supportsDraftWorkflow(), fn ($q) => $q->submitted())
            ->select('field_coordinator_user_id', 'field_coordinator_name')
            ->distinct()
            ->orderBy('field_coordinator_name')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->field_coordinator_user_id,
                'name' => (string) $r->field_coordinator_name,
            ])
            ->unique('id')
            ->values()
            ->all();

        return view('staff.attendance.view', [
            'reports' => $reports,
            'user' => $user,
            'blockOptions' => $blockOptions,
            'coordinatorOptions' => $coordinatorOptions,
            'migrationMissing' => false,
            'totalWorkshops' => (int) ($stats->total_workshops ?? 0),
            'totalMale' => (int) ($stats->total_male ?? 0),
            'totalFemale' => (int) ($stats->total_female ?? 0),
            'totalParticipants' => (int) ($stats->total_participants ?? 0),
        ]);
    }

    public function show(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): View {
        $user = $request->user()->load('district');
        abort_unless($this->canViewFieldVisit($user, $attendanceReport), 403);

        $attendanceReport->load(['district', 'gramPanchayat', 'districtBlock', 'coordinator']);

        return view('staff.attendance.show', [
            'report' => $attendanceReport,
            'user' => $user,
            'participantRows' => $attendanceReport->participantRows(),
            'mediaItems' => $attendanceReport->visitMediaItems(),
        ]);
    }

    public function exportParticipants(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): StreamedResponse {
        $user = $request->user()->load('district');
        abort_unless($this->canViewFieldVisit($user, $attendanceReport), 403);

        $attendanceReport->load(['district', 'gramPanchayat', 'districtBlock']);
        $rows = $attendanceReport->participantRows();

        $filename = 'participants-workshop-'.$attendanceReport->id.'-'
            .($attendanceReport->visit_date?->format('Y-m-d') ?? 'unknown').'.xlsx';

        if (class_exists(Spreadsheet::class)) {
            return $this->exportParticipantsXlsx($attendanceReport, $rows, $filename);
        }

        return $this->exportParticipantsCsv($rows, $filename);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function exportParticipantsXlsx(
        FieldCoordinatorAttendanceReport $report,
        array $rows,
        string $filename,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Participants');

        $headers = ['#', 'Name', 'Mobile', 'Gender', 'District', 'Block', 'Gram Panchayat'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ]);
            $col++;
        }

        foreach ($rows as $i => $row) {
            $r = $i + 2;
            $sheet->setCellValue('A'.$r, (int) ($row['sr'] ?? $i + 1));
            $sheet->setCellValue('B'.$r, (string) ($row['name'] ?? ''));
            $sheet->setCellValueExplicit('C'.$r, (string) ($row['mobile'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D'.$r, (string) ($row['gender'] ?? ''));
            $sheet->setCellValue('E'.$r, (string) ($row['district_name'] ?? ''));
            $sheet->setCellValue('F'.$r, (string) ($row['block_name'] ?? ''));
            $sheet->setCellValue('G'.$r, (string) ($row['gram_panchayat_name'] ?? ''));
        }

        $filename = str_replace('.xlsx', '', $filename).'.xlsx';

        return response()->streamDownload(
            static function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function exportParticipantsCsv(array $rows, string $filename): StreamedResponse
    {
        $filename = str_replace('.xlsx', '.csv', $filename);

        return response()->streamDownload(
            static function () use ($rows): void {
                $out = fopen('php://output', 'w');
                if (! $out) {
                    return;
                }
                fputcsv($out, ['#', 'Name', 'Mobile', 'Gender', 'District', 'Block', 'Gram Panchayat']);
                foreach ($rows as $i => $row) {
                    fputcsv($out, [
                        (int) ($row['sr'] ?? $i + 1),
                        (string) ($row['name'] ?? ''),
                        (string) ($row['mobile'] ?? ''),
                        (string) ($row['gender'] ?? ''),
                        (string) ($row['district_name'] ?? ''),
                        (string) ($row['block_name'] ?? ''),
                        (string) ($row['gram_panchayat_name'] ?? ''),
                    ]);
                }
                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv'],
        );
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

        $createPayload = [
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
        ];

        if (FieldCoordinatorAttendanceReport::supportsDraftWorkflow()) {
            $createPayload['status'] = FieldCoordinatorAttendanceReport::STATUS_SUBMITTED;
            $createPayload['participants_json'] = $this->participantRowsService->syncRowCount(
                null,
                $male,
                $female,
                (string) ($user->district?->name ?? ''),
                (string) $block->name,
                $gramPanchayat?->id,
                (string) ($gramPanchayat?->name ?? ''),
            );
        }

        FieldCoordinatorAttendanceReport::query()->create($createPayload);

        $status = 'Block level workshop submitted.';

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

    public function edit(FieldCoordinatorAttendanceReport $attendanceReport, Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless((int) $attendanceReport->field_coordinator_user_id === (int) $user->id, 403);

        if ($attendanceReport->isDraft()) {
            return redirect()->route('staff.attendance.index', ['draft' => $attendanceReport->id]);
        }

        return redirect()->route('staff.attendance.index', ['edit' => $attendanceReport->id]);
    }

    public function update(
        FieldCoordinatorAttendanceReport $attendanceReport,
        Request $request,
    ): RedirectResponse {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmitFieldVisit($user), 403);
        abort_unless($this->canModifySubmitted($user, $attendanceReport), 403);

        $districtId = (int) ($user->district_id ?: 0);

        $rules = [
            'visit_date' => ['required', 'date'],
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'gram_panchayat_id' => ['required', 'integer', 'exists:gram_panchayats,id'],
            'area' => ['required', 'string', 'max:191'],
            'participants_male_count' => ['required', 'integer', 'min:0'],
            'participants_female_count' => ['required', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'attendance_sheet' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ];

        if (! Schema::hasTable('gram_panchayats')) {
            $rules['gram_panchayat_id'] = ['nullable'];
        }

        $validated = $request->validate($rules);

        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 403);

        $gramPanchayat = null;
        if (Schema::hasTable('gram_panchayats') && ! empty($validated['gram_panchayat_id'])) {
            $gramPanchayat = GramPanchayat::query()->findOrFail((int) $validated['gram_panchayat_id']);
            abort_unless((int) $gramPanchayat->district_block_id === (int) $block->id, 422);
        }

        $mediaItems = $attendanceReport->visitMediaItems();

        if (! $request->has('skip_media_check') && count($mediaItems) === 0) {
            $newMedia = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
            $mediaItems = $this->mediaStorage->mergeOntoReport($attendanceReport, $newMedia);
        }

        if (count($mediaItems) === 0) {
            return back()
                ->withErrors(['visit_media' => 'Upload at least one workshop photo.'])
                ->withInput();
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

        $sheetPayload = [];
        $sheetFile = $request->file('attendance_sheet');

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

        if ($sheetFile instanceof UploadedFile) {
            if ($participantsTotal <= 0) {
                return back()
                    ->withErrors(['attendance_sheet' => 'Set participant counts before uploading an attendance sheet.'])
                    ->withInput();
            }

            $districtName = (string) ($user->district?->name ?? '');
            $gpName = (string) ($gramPanchayat?->name ?? '');

            $this->attendanceSheetService->assertValidUpload(
                $sheetFile,
                $participantsTotal,
                $male,
                $female,
                $districtName,
                (string) $block->name,
                $gpName,
            );

            if ($attendanceReport->attendance_sheet_path) {
                Storage::delete($attendanceReport->attendance_sheet_path);
            }

            $sheetPayload = $this->attendanceSheetService->storeUploadedFile($sheetFile);
        }

        $districtName = (string) ($user->district?->name ?? $attendanceReport->district?->name ?? '');
        $gpName = (string) ($gramPanchayat?->name ?? '');
        $rows = $this->participantRowsService->syncRowCount(
            $attendanceReport->participantRows(),
            $male,
            $female,
            $districtName,
            (string) $block->name,
            $gramPanchayat?->id,
            $gpName !== '' ? $gpName : null,
        );

        if ($request->has('participants') && is_array($request->input('participants'))) {
            $rows = $this->participantRowsService->sanitizeIncoming(
                $request->input('participants'),
                $participantsTotal,
            );
        }

        $attendanceReport->update([
            'visit_date' => $validated['visit_date'],
            'block' => (string) $block->name,
            'district_block_id' => (int) $block->id,
            'gram_panchayat_id' => $gramPanchayat?->id,
            'area' => $validated['area'],
            'remark' => $validated['remark'] ?? null,
            'visit_media_json' => $mediaItems,
            'participants_male_count' => $male,
            'participants_female_count' => $female,
            'participants_total' => $participantsTotal,
            'participants_json' => $rows,
            'district_id' => $districtId > 0 ? $districtId : null,
            ...$sheetPayload,
        ]);

        return redirect()
            ->route('staff.attendance.index')
            ->with('status', 'Workshop updated.');
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

    private function assertOwnDraft(User $user, FieldCoordinatorAttendanceReport $report): void
    {
        abort_unless((int) $report->field_coordinator_user_id === (int) $user->id, 403);
        abort_unless($report->isDraft(), 422, 'This workshop is not a draft.');
    }

    private function assertOwnEditable(User $user, FieldCoordinatorAttendanceReport $report): void
    {
        abort_unless((int) $report->field_coordinator_user_id === (int) $user->id, 403);
        abort_unless($report->isDraft() || $report->isSubmitted(), 422);
    }

    private function canModifySubmitted(User $user, FieldCoordinatorAttendanceReport $report): bool
    {
        return $this->canSubmitFieldVisit($user)
            && $report->isSubmitted()
            && (int) $report->field_coordinator_user_id === (int) $user->id;
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
