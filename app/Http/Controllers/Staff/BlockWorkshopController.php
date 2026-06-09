<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Http\Controllers\Controller;
use App\Models\BlockWorkshop;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use App\Services\BlockWorkshopParticipantRowsService;
use App\Support\WorkshopDashboardCsvExport;
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

class BlockWorkshopController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private readonly FieldVisitMediaStorage $mediaStorage,
        private readonly FieldVisitAttendanceSheetService $attendanceSheetService,
        private readonly BlockWorkshopParticipantRowsService $participantRowsService,
    ) {}

    // ── Form / Index ──────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmit($user), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blockRows = $districtId > 0
            ? DistrictBlock::query()
                ->where('district_id', $districtId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        if (! Schema::hasTable('block_workshops')) {
        return view('staff.attendance.index', [
            'reports' => collect(),
            'user' => $user,
            'blockRows' => $blockRows,
            'gramPanchayatsEnabled' => false,
            'migrationMissing' => true,
            'draftWorkflow' => false,
            'activeDraft' => null,
            'routePrefix' => 'staff.workshops',
            'modelParam' => 'blockWorkshop',
        ]);
        }

        $editId = (int) $request->query('edit', 0);
        $editingSubmitted = false;
        $activeDraft = null;

        if ($editId > 0) {
            $activeDraft = BlockWorkshop::query()
                ->submitted()
                ->where('field_coordinator_user_id', (int) $user->id)
                ->whereKey($editId)
                ->with(['district', 'gramPanchayat', 'districtBlock'])
                ->firstOrFail();
            $editingSubmitted = true;
        }

        if ($activeDraft === null) {
            $draftId = (int) $request->query('draft', 0);

            if ($draftId > 0) {
                $activeDraft = BlockWorkshop::query()
                    ->draft()
                    ->where('field_coordinator_user_id', (int) $user->id)
                    ->whereKey($draftId)
                    ->with(['district', 'gramPanchayat', 'districtBlock'])
                    ->first();
            }

            if ($activeDraft === null) {
                $activeDraft = BlockWorkshop::query()
                    ->draft()
                    ->where('field_coordinator_user_id', (int) $user->id)
                    ->with(['district', 'gramPanchayat', 'districtBlock'])
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }

        $reports = BlockWorkshop::query()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->submitted()
            ->with(['district', 'gramPanchayat', 'coordinator.designationRecord'])
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
            'draftWorkflow' => true,
            'activeDraft' => $activeDraft,
            'editingSubmitted' => $editingSubmitted,
            'routePrefix' => 'staff.workshops',
            'modelParam' => 'blockWorkshop',
        ]);
    }

    // ── Draft creation ────────────────────────────────────────────────────────

    public function createDraft(Request $request): JsonResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user), 403);

        if (! Schema::hasTable('block_workshops')) {
            return response()->json(['message' => 'Block workshop table not found. Run migrations.'], 503);
        }

        $existing = BlockWorkshop::query()
            ->draft()
            ->where('field_coordinator_user_id', (int) $user->id)
            ->orderByDesc('updated_at')
            ->first();

        if ($existing) {
            return response()->json([
                'id' => $existing->id,
                'redirect_url' => route('staff.workshops.index', ['draft' => $existing->id]),
            ]);
        }

        $districtId = (int) ($user->district_id ?: 0);

        $draft = BlockWorkshop::query()->create([
            'field_coordinator_user_id' => (int) $user->id,
            'field_coordinator_name' => (string) $user->name,
            'visit_date' => now()->toDateString(),
            'entry_date' => now()->toDateString(),
            'district_id' => $districtId > 0 ? $districtId : null,
            'status' => BlockWorkshop::STATUS_DRAFT,
            'participants_male_count' => 0,
            'participants_female_count' => 0,
            'participants_total' => 0,
            'participants_json' => [],
        ]);

        return response()->json([
            'id' => $draft->id,
            'redirect_url' => route('staff.workshops.index', ['draft' => $draft->id]),
        ], 201);
    }

    // ── Draft meta update ─────────────────────────────────────────────────────

    public function updateDraftMeta(BlockWorkshop $blockWorkshop, Request $request): JsonResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user), 403);
        $this->assertOwnEditable($user, $blockWorkshop);

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
        $male = (int) ($validated['participants_male_count'] ?? $blockWorkshop->participants_male_count);
        $female = (int) ($validated['participants_female_count'] ?? $blockWorkshop->participants_female_count);
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
        $blockName = (string) ($block?->name ?? $blockWorkshop->block ?? '');
        $gpId = $gramPanchayat?->id ?? $blockWorkshop->gram_panchayat_id;
        $gpName = (string) ($gramPanchayat?->name ?? $blockWorkshop->gramPanchayat?->name ?? '');

        $rows = $this->participantRowsService->syncRowCount(
            $blockWorkshop->participantRows(),
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
        if ($block) {
            $payload['block'] = (string) $block->name;
            $payload['district_block_id'] = (int) $block->id;
        }
        if ($gramPanchayat) {
            $payload['gram_panchayat_id'] = (int) $gramPanchayat->id;
        }
        if (array_key_exists('remark', $validated)) {
            $payload['remark'] = $validated['remark'];
        }

        $blockWorkshop->update($payload);

        return response()->json([
            'ok' => true,
            'row_count' => count($rows),
            'total' => $total,
        ]);
    }

    // ── Participants save ─────────────────────────────────────────────────────

    public function saveParticipants(BlockWorkshop $blockWorkshop, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        $this->assertOwnEditable($user, $blockWorkshop);

        $incoming = $request->input('participants');
        abort_if(! is_array($incoming), 422, 'participants must be an array');

        $total = (int) $blockWorkshop->participants_total;
        $rows = $this->participantRowsService->sanitizeIncoming($incoming, $total ?: count($incoming));
        $blockWorkshop->update(['participants_json' => $rows]);

        return response()->json(['ok' => true, 'saved' => count($rows)]);
    }

    // ── Photos ────────────────────────────────────────────────────────────────

    public function uploadPhotos(BlockWorkshop $blockWorkshop, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        $this->assertOwnEditable($user, $blockWorkshop);

        $current = count($blockWorkshop->visitMediaItems());
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

        $merged = $this->mediaStorage->mergeOntoReport($blockWorkshop, $newItems);
        $blockWorkshop->update(['visit_media_json' => $merged]);

        return response()->json([
            'ok' => true,
            'count' => count($merged),
            'items' => $this->photoItemsForJson($blockWorkshop->fresh()),
        ]);
    }

    public function deletePhoto(BlockWorkshop $blockWorkshop, int $photoIndex, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        $this->assertOwnEditable($user, $blockWorkshop);

        $items = $this->mediaStorage->removeAt($blockWorkshop, $photoIndex);
        $blockWorkshop->update(['visit_media_json' => $items]);

        return response()->json([
            'ok' => true,
            'count' => count($items),
            'items' => $this->photoItemsForJson($blockWorkshop->fresh()),
        ]);
    }

    public function downloadAttachment(BlockWorkshop $blockWorkshop, Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canView($user, $blockWorkshop), 403);

        $index = $request->query('index');
        if ($index !== null && $index !== '') {
            return $this->mediaStorage->download($blockWorkshop, (int) $index, $request->boolean('inline'));
        }

        return $this->mediaStorage->legacyDownload($blockWorkshop);
    }

    // ── Submit draft ──────────────────────────────────────────────────────────

    public function redirectSubmitPage(BlockWorkshop $blockWorkshop, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        abort_unless((int) $blockWorkshop->field_coordinator_user_id === (int) $user->id, 403);

        if ($blockWorkshop->isDraft()) {
            return redirect()->route('staff.workshops.index', ['draft' => $blockWorkshop->id]);
        }

        return redirect()->route('staff.workshops.show', $blockWorkshop);
    }

    public function submitDraft(BlockWorkshop $blockWorkshop, Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmit($user), 403);
        $this->assertOwnDraft($user, $blockWorkshop);

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

        $mediaItems = $blockWorkshop->visitMediaItems();

        if (! $request->has('skip_media_check') && count($mediaItems) === 0) {
            // Try to re-merge from any uploaded files in this request
            $newMedia = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
            $mediaItems = $this->mediaStorage->mergeOntoReport($blockWorkshop, $newMedia);
        }

        if (count($mediaItems) === 0) {
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
            $blockWorkshop->participantRows(),
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

        $blockWorkshop->update([
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
            'status' => BlockWorkshop::STATUS_SUBMITTED,
            ...$sheetPayload,
        ]);

        $status = 'Block level workshop submitted.';

        return redirect()
            ->route('staff.workshops.index')
            ->with('status', $status)
            ->setStatusCode(303);
    }

    // ── View dashboard ────────────────────────────────────────────────────────

    public function view(Request $request): View
    {
        $user = $request->user()->load(['district', 'designationRecord']);

        if (! Schema::hasTable('block_workshops')) {
        return view('staff.attendance.view', [
            'reports' => collect(),
            'user' => $user,
            'blockOptions' => [],
            'migrationMissing' => true,
            'totalWorkshops' => 0,
            'totalMale' => 0,
            'totalFemale' => 0,
            'totalParticipants' => 0,
            'routePrefix' => 'staff.workshops',
            'modelParam' => 'blockWorkshop',
        ]);
        }

        $districtId = (int) ($user->district_id ?: 0);

        $baseQuery = fn () => BlockWorkshop::query()
            ->where('district_id', $districtId)
            ->submitted();

        $query = $baseQuery()->with(['district', 'gramPanchayat', 'coordinator.designationRecord']);

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

        $coordinatorOptions = BlockWorkshop::query()
            ->where('district_id', $districtId)
            ->submitted()
            ->select('field_coordinator_user_id', 'field_coordinator_name')
            ->distinct()
            ->orderBy('field_coordinator_name')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->field_coordinator_user_id, 'name' => (string) $r->field_coordinator_name])
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
            'routePrefix' => 'staff.workshops',
            'modelParam' => 'blockWorkshop',
            'exportRoute' => 'staff.workshops.export',
        ]);
    }

    public function exportList(Request $request): StreamedResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless(Schema::hasTable('block_workshops'), 404);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 403);

        $query = BlockWorkshop::query()
            ->where('district_id', $districtId)
            ->submitted()
            ->with(['district', 'gramPanchayat', 'coordinator.designationRecord']);

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

        $rows = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        return WorkshopDashboardCsvExport::blockWorkshopsStaff(
            $rows,
            'block-workshops-'.now()->format('Ymd_His').'.csv'
        );
    }

    // ── Show detail ───────────────────────────────────────────────────────────

    public function show(BlockWorkshop $blockWorkshop, Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canView($user, $blockWorkshop), 403);

        $blockWorkshop->load(['district', 'gramPanchayat', 'districtBlock', 'coordinator']);

        return view('staff.attendance.show', [
            'report' => $blockWorkshop,
            'user' => $user,
            'participantRows' => $blockWorkshop->participantRows(),
            'mediaItems' => $blockWorkshop->visitMediaItems(),
            'routePrefix' => 'staff.workshops',
            'modelParam' => 'blockWorkshop',
        ]);
    }

    public function edit(BlockWorkshop $blockWorkshop, Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmit($user), 403);
        abort_unless($this->canModify($user, $blockWorkshop), 403);

        if ($blockWorkshop->isDraft()) {
            return redirect()->route('staff.workshops.index', ['draft' => $blockWorkshop->id]);
        }

        return redirect()->route('staff.workshops.index', ['edit' => $blockWorkshop->id]);
    }

    public function update(BlockWorkshop $blockWorkshop, Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmit($user), 403);
        abort_unless($this->canModify($user, $blockWorkshop), 403);

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

        $mediaItems = $blockWorkshop->visitMediaItems();

        if (! $request->has('skip_media_check') && count($mediaItems) === 0) {
            $newMedia = $this->mediaStorage->storeMany((array) $request->file('visit_media', []));
            $mediaItems = $this->mediaStorage->mergeOntoReport($blockWorkshop, $newMedia);
        }

        if (count($mediaItems) === 0) {
            return back()
                ->withErrors(['visit_media' => 'Upload at least one workshop photo.'])
                ->withInput();
        }

        $male = (int) $validated['participants_male_count'];
        $female = (int) $validated['participants_female_count'];
        $participantsTotal = $male + $female;

        $blockWorkshop->load(['district', 'gramPanchayat']);

        $locationOrCountsChanged = (int) $blockWorkshop->participants_male_count !== $male
            || (int) $blockWorkshop->participants_female_count !== $female
            || (int) $blockWorkshop->participants_total !== $participantsTotal
            || (int) $blockWorkshop->district_block_id !== (int) $block->id
            || (int) $blockWorkshop->gram_panchayat_id !== (int) ($gramPanchayat?->id ?: 0)
            || (string) $blockWorkshop->area !== (string) $validated['area']
            || (string) $blockWorkshop->block !== (string) $block->name;

        $sheetPayload = [];
        $sheetFile = $request->file('attendance_sheet');

        if ($locationOrCountsChanged && $blockWorkshop->hasAttendanceSheet()) {
            $sheetPath = (string) $blockWorkshop->attendance_sheet_path;
            if ($sheetPath !== '' && Storage::exists($sheetPath)) {
                Storage::delete($sheetPath);
            }
            $blockWorkshop->attendance_sheet_path = null;
            $blockWorkshop->attendance_sheet_original_name = null;
            $blockWorkshop->attendance_sheet_mime = null;
            $blockWorkshop->attendance_sheet_size_bytes = null;
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

            if ($blockWorkshop->attendance_sheet_path) {
                Storage::delete($blockWorkshop->attendance_sheet_path);
            }

            $sheetPayload = $this->attendanceSheetService->storeUploadedFile($sheetFile);
        }

        $districtName = (string) ($user->district?->name ?? $blockWorkshop->district?->name ?? '');
        $gpName = (string) ($gramPanchayat?->name ?? '');
        $rows = $this->participantRowsService->syncRowCount(
            $blockWorkshop->participantRows(),
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

        $blockWorkshop->update([
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

        $status = 'Workshop updated.';

        return redirect()
            ->route('staff.workshops.view')
            ->with('status', $status)
            ->setStatusCode(303);
    }

    // ── Export participants ───────────────────────────────────────────────────

    public function exportParticipants(BlockWorkshop $blockWorkshop, Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canView($user, $blockWorkshop), 403);

        $blockWorkshop->load(['district', 'gramPanchayat', 'districtBlock']);
        $rows = $blockWorkshop->participantRows();

        $filename = 'participants-workshop-'.$blockWorkshop->id.'-'
            .($blockWorkshop->visit_date?->format('Y-m-d') ?? 'unknown').'.xlsx';

        if (class_exists(Spreadsheet::class)) {
            return $this->exportXlsx($rows, $filename);
        }

        return $this->exportCsv($rows, str_replace('.xlsx', '.csv', $filename));
    }

    // ── Attendance sheet ──────────────────────────────────────────────────────

    public function downloadAttendanceSheet(BlockWorkshop $blockWorkshop, Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canView($user, $blockWorkshop), 403);
        abort_unless($blockWorkshop->hasAttendanceSheet(), 404);

        return $this->attendanceSheetService->downloadStored(
            (string) $blockWorkshop->attendance_sheet_path,
            (string) ($blockWorkshop->attendance_sheet_original_name ?: 'attendance-sheet.xlsx'),
        );
    }

    public function uploadAttendanceSheet(BlockWorkshop $blockWorkshop, Request $request): RedirectResponse
    {
        $user = $request->user()->load(['district', 'designationRecord']);
        abort_unless($this->canSubmit($user), 403);
        abort_unless((int) $blockWorkshop->field_coordinator_user_id === (int) $user->id, 403);

        $participantsTotal = (int) $blockWorkshop->participants_total;
        abort_if($participantsTotal <= 0, 422);

        $request->validate([
            'attendance_sheet' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $sheetFile = $request->file('attendance_sheet');
        abort_if(! $sheetFile instanceof UploadedFile, 422);

        $blockWorkshop->load(['district', 'gramPanchayat']);

        $this->attendanceSheetService->assertValidUpload(
            $sheetFile,
            $participantsTotal,
            (int) $blockWorkshop->participants_male_count,
            (int) $blockWorkshop->participants_female_count,
            (string) ($blockWorkshop->district?->name ?? ''),
            (string) ($blockWorkshop->block ?? ''),
            $blockWorkshop->attendanceSheetGramPanchayatLabel(),
        );

        if ($blockWorkshop->attendance_sheet_path) {
            Storage::delete($blockWorkshop->attendance_sheet_path);
        }

        $blockWorkshop->update($this->attendanceSheetService->storeUploadedFile($sheetFile));

        return redirect()
            ->route('staff.workshops.show', $blockWorkshop)
            ->with('status', 'Attendance sheet uploaded.');
    }

    public function downloadAttendanceSheetTemplate(Request $request): StreamedResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user), 403);

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

        return $this->attendanceSheetService->streamTemplateDownload(
            (int) $validated['participants_male_count'] + (int) $validated['participants_female_count'],
            (string) ($user->district?->name ?? ''),
            (string) $block->name,
            (string) $gramPanchayat->name,
        );
    }

    public function downloadAttendanceSheetTemplateForReport(BlockWorkshop $blockWorkshop, Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        abort_unless((int) $blockWorkshop->field_coordinator_user_id === (int) $user->id, 403);

        $blockWorkshop->load(['district', 'gramPanchayat']);

        $male = (int) $blockWorkshop->participants_male_count;
        $female = (int) $blockWorkshop->participants_female_count;
        $total = (int) $blockWorkshop->participants_total ?: ($male + $female);
        abort_if($total <= 0, 422, 'This submission has no participants — template is not required.');

        return $this->attendanceSheetService->streamTemplateDownload(
            $total,
            (string) ($blockWorkshop->district?->name ?? $user->district?->name ?? ''),
            (string) ($blockWorkshop->block ?? ''),
            $blockWorkshop->attendanceSheetGramPanchayatLabel(),
        );
    }

    // ── Gram panchayat autocomplete ───────────────────────────────────────────

    public function gramPanchayats(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        abort_unless(Schema::hasTable('gram_panchayats'), 404);

        $blockId = (int) $request->query('district_block_id', 0);
        abort_if($blockId <= 0, 422);

        $block = DistrictBlock::query()->findOrFail($blockId);
        abort_unless((int) $block->district_id === (int) ($user->district_id ?: 0), 403);

        $items = GramPanchayat::listForBlock($blockId, (string) $request->query('q', ''));

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(BlockWorkshop $blockWorkshop, Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canSubmit($user), 403);
        abort_unless((int) $blockWorkshop->field_coordinator_user_id === (int) $user->id, 403);

        $sheetPath = (string) ($blockWorkshop->attendance_sheet_path ?? '');
        if ($sheetPath !== '' && Storage::exists($sheetPath)) {
            Storage::delete($sheetPath);
        }

        $this->mediaStorage->deleteAllForReport($blockWorkshop);
        $blockWorkshop->delete();

        $redirectRoute = $request->boolean('from_view')
            ? 'staff.workshops.view'
            : 'staff.workshops.index';

        return redirect()
            ->route($redirectRoute)
            ->with('status', 'Workshop deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertOwnDraft(\App\Models\User $user, BlockWorkshop $workshop): void
    {
        abort_unless((int) $workshop->field_coordinator_user_id === (int) $user->id, 403);
        abort_unless($workshop->isDraft(), 422, 'This workshop is not a draft.');
    }

    private function assertOwnEditable(\App\Models\User $user, BlockWorkshop $workshop): void
    {
        abort_unless((int) $workshop->field_coordinator_user_id === (int) $user->id, 403);
        abort_unless($workshop->isDraft() || $workshop->isSubmitted(), 422);
    }

    private function canSubmit(\App\Models\User $user): bool
    {
        return $user->role === 'district_staff'
            && (int) ($user->district_id ?: 0) > 0;
    }

    private function canView(\App\Models\User $user, BlockWorkshop $workshop): bool
    {
        if ($user->role !== 'district_staff') {
            return false;
        }
        $districtId = (int) ($user->district_id ?: 0);

        return (int) $workshop->field_coordinator_user_id === (int) $user->id
            || ($districtId > 0 && (int) ($workshop->district_id ?: 0) === $districtId);
    }

    private function canModify(\App\Models\User $user, BlockWorkshop $workshop): bool
    {
        return $this->canSubmit($user)
            && $workshop->isSubmitted()
            && (int) $workshop->field_coordinator_user_id === (int) $user->id;
    }

    /** @return list<array{index: int, url: string, name: string}> */
    private function photoItemsForJson(BlockWorkshop $workshop): array
    {
        return collect($workshop->visitMediaItems())
            ->values()
            ->map(fn (array $item, int $index) => [
                'index' => $index,
                'url' => route('staff.workshops.attachment', [
                    'blockWorkshop' => $workshop,
                    'index' => $index,
                    'inline' => 1,
                ]),
                'name' => (string) ($item['original_name'] ?? 'photo'),
            ])
            ->all();
    }

    /** @param list<array<string, mixed>> $rows */
    private function exportXlsx(array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Participants');

        $headers = ['#', 'Name', 'Mobile', 'Gender', 'District', 'Block', 'Gram Panchayat'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'1', $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($col.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
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

        return response()->streamDownload(
            static function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function exportCsv(array $rows, string $filename): StreamedResponse
    {
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
}
