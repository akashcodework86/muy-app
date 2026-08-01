<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesWorkshopParticipantRows;
use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use App\Models\LakhpatiTechnicalTraining;
use App\Support\MisFieldActivityApproval;
use App\Support\TodayOnlyDate;
use App\Support\WorkshopDashboardCsvExport;
use App\Services\MisFieldActivityWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LakhpatiTechnicalTrainingController extends Controller
{
    use ResolvesWorkshopParticipantRows;
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private MisFieldActivityWorkflowService $misFieldWorkflow,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        $participantContext = $this->workshopParticipantFormContext($user);

        return view('staff.lakhpati-technical-trainings.form', [
            'user' => $user,
            'migrationMissing' => ! Schema::hasTable('potential_lakhpati_technical_trainings'),
            'agencyTypes' => LakhpatiTechnicalTraining::AGENCY_TYPES,
            ...$participantContext,
            'initialRows' => [],
            'defaultBlockId' => 0,
            'defaultGpId' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        if (! Schema::hasTable('potential_lakhpati_technical_trainings')) {
            return redirect()
                ->route('staff.lakhpati-technical-trainings.create')
                ->withErrors(['session_title' => 'Table not found. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request);
        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required.');

        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 403);

        $male = (int) ($validated['male_participants'] ?? 0);
        $female = (int) ($validated['female_participants'] ?? 0);
        $total = $male + $female;
        $this->assertWorkshopParticipantLocation($request, $male, $female);
        $participantRows = $this->resolveWorkshopParticipantsFromRequest($request, $user, $male, $female);

        $mediaItems = $this->storeUploadedMedia((array) $request->file('attendance_media', []));
        $photoItems = $this->storeUploadedPhotos((array) $request->file('workshop_photos', []));

        $session = LakhpatiTechnicalTraining::query()->create([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'session_date' => $validated['session_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'district_block_id' => (int) $block->id,
            'block' => (string) $block->name,
            'gram_panchayat_id' => $this->resolvedGramPanchayatId($validated),
            'area' => trim((string) $validated['area']),
            'workshop_mode' => (string) $validated['workshop_mode'],
            'requesting_agency_type' => (string) $validated['requesting_agency_type'],
            'session_title' => trim((string) $validated['session_title']),
            'session_brief' => trim((string) ($validated['session_brief'] ?? '')) ?: null,
            'male_participants' => $male,
            'female_participants' => $female,
            'participants_total' => $total,
            'participants_json' => $participantRows,
            'attendance_media_json' => $mediaItems,
            'workshop_photos_json' => $photoItems,
        ]);

        $this->misFieldWorkflow->submitForApproval($session, (int) $user->id);

        return redirect()
            ->route('staff.lakhpati-technical-trainings.dashboard')
            ->with('status', 'Technical training session (3.3.1) submitted for approval.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard($user->role), 403);

        if (! Schema::hasTable('potential_lakhpati_technical_trainings')) {
            return view('staff.lakhpati-technical-trainings.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'totals' => ['sessions' => 0, 'male' => 0, 'female' => 0, 'participants' => 0, 'with_photos' => 0],
            ]);
        }

        $query = LakhpatiTechnicalTraining::query()->with(['district:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']);

        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('block', 'like', $like)
                    ->orWhere('area', 'like', $like)
                    ->orWhere('session_title', 'like', $like)
                    ->orWhere('session_brief', 'like', $like)
                    ->orWhere('requesting_agency_type', 'like', $like);
            });
        }

        if ($request->filled('agency')) {
            $query->where('requesting_agency_type', (string) $request->query('agency'));
        }

        if ($request->filled('from')) {
            $query->whereDate('session_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('session_date', '<=', (string) $request->query('to'));
        }

        $eventPeriod = $this->applyEventPeriodFilter($query, $request);

        $totals = [
            'sessions' => (int) (clone $query)->count(),
            'male' => (int) (clone $query)->sum('male_participants'),
            'female' => (int) (clone $query)->sum('female_participants'),
            'with_photos' => 0,
        ];
        $totals['participants'] = $totals['male'] + $totals['female'];
        if (Schema::hasColumn('potential_lakhpati_technical_trainings', 'workshop_photos_json')) {
            $totals['with_photos'] = (int) (clone $query)
                ->whereNotNull('workshop_photos_json')
                ->where('workshop_photos_json', '!=', '[]')
                ->where('workshop_photos_json', '!=', 'null')
                ->count();
        }

        $rows = $query
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $filterYear = (int) ($eventPeriod['event_year'] !== '' ? $eventPeriod['event_year'] : now()->year);

        return view('staff.lakhpati-technical-trainings.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'currentRole' => (string) $user->role,
            'yearOptions' => $this->yearOptions(),
            'monthOptions' => $this->monthOptions($filterYear),
            'agencyTypes' => LakhpatiTechnicalTraining::AGENCY_TYPES,
            'filters' => [
                'q' => $search,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'event_year' => $eventPeriod['event_year'],
                'event_month' => $eventPeriod['event_month'],
                'agency' => (string) $request->query('agency', ''),
            ],
            'totals' => $totals,
            'exportRoute' => $this->exportRouteForRole((string) $user->role),
        ]);
    }

    public function show(Request $request, LakhpatiTechnicalTraining $lakhpatiTechnicalTraining): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $lakhpatiTechnicalTraining);

        $lakhpatiTechnicalTraining->loadMissing(['district:id,name', 'submitter:id,name', 'districtBlock:id,name', 'gramPanchayat:id,name']);

        return view('staff.lakhpati-technical-trainings.show', [
            'row' => $lakhpatiTechnicalTraining,
            'currentRole' => (string) $user->role,
            'canEdit' => MisFieldActivityApproval::submitterCanEdit($user, $lakhpatiTechnicalTraining),
            'canWithdraw' => MisFieldActivityApproval::submitterCanWithdraw($user, $lakhpatiTechnicalTraining),
        ]);
    }

    public function edit(Request $request, LakhpatiTechnicalTraining $lakhpatiTechnicalTraining): View
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($lakhpatiTechnicalTraining, (int) $user->id);

        $participantContext = $this->workshopParticipantFormContext($user);
        $firstRow = $lakhpatiTechnicalTraining->participantRows()[0] ?? [];

        return view('staff.lakhpati-technical-trainings.edit', [
            'user' => $user,
            'row' => $lakhpatiTechnicalTraining,
            'agencyTypes' => LakhpatiTechnicalTraining::AGENCY_TYPES,
            ...$participantContext,
            'initialRows' => $lakhpatiTechnicalTraining->participantRows(),
            'defaultBlockId' => (int) ($lakhpatiTechnicalTraining->district_block_id ?: $this->defaultBlockIdFromParticipantRows($participantContext['blockRows'], $firstRow)),
            'defaultGpId' => (int) ($lakhpatiTechnicalTraining->gram_panchayat_id ?: ($firstRow['gram_panchayat_id'] ?? 0)),
        ]);
    }

    public function update(Request $request, LakhpatiTechnicalTraining $lakhpatiTechnicalTraining): RedirectResponse
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($lakhpatiTechnicalTraining, (int) $user->id);

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request, $lakhpatiTechnicalTraining->session_date?->toDateString());
        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required.');

        $block = DistrictBlock::query()->findOrFail((int) $validated['district_block_id']);
        abort_unless((int) $block->district_id === $districtId, 403);

        $newUploads = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($newUploads !== []) {
            $existingMedia = collect((array) $lakhpatiTechnicalTraining->attendance_media_json)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
            $combinedCount = $existingMedia->count() + count($newUploads);
            abort_if($combinedCount > 25, 422, 'You can upload up to 25 attendance files per session.');

            $lakhpatiTechnicalTraining->attendance_media_json = $existingMedia
                ->merge($this->storeUploadedMedia($newUploads))
                ->values()
                ->all();
        }

        $newPhotos = array_values(array_filter((array) $request->file('workshop_photos', [])));
        if ($newPhotos !== []) {
            $existingPhotos = collect((array) $lakhpatiTechnicalTraining->workshop_photos_json)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
            $combinedPhotoCount = $existingPhotos->count() + count($newPhotos);
            if ($combinedPhotoCount > 5) {
                return back()
                    ->withInput()
                    ->withErrors(['workshop_photos' => 'You can upload up to 5 workshop photos per session.']);
            }

            $lakhpatiTechnicalTraining->workshop_photos_json = $existingPhotos
                ->merge($this->storeUploadedPhotos($newPhotos))
                ->values()
                ->all();
        }

        $male = (int) ($validated['male_participants'] ?? 0);
        $female = (int) ($validated['female_participants'] ?? 0);
        $total = $male + $female;
        $this->assertWorkshopParticipantLocation($request, $male, $female);
        $participantRows = $this->resolveWorkshopParticipantsFromRequest(
            $request,
            $user,
            $male,
            $female,
            $lakhpatiTechnicalTraining->participantRows(),
        );

        $lakhpatiTechnicalTraining->fill([
            'session_date' => $validated['session_date'],
            'district_block_id' => (int) $block->id,
            'block' => (string) $block->name,
            'gram_panchayat_id' => $this->resolvedGramPanchayatId($validated),
            'area' => trim((string) $validated['area']),
            'workshop_mode' => (string) $validated['workshop_mode'],
            'requesting_agency_type' => (string) $validated['requesting_agency_type'],
            'session_title' => trim((string) $validated['session_title']),
            'session_brief' => trim((string) ($validated['session_brief'] ?? '')) ?: null,
            'male_participants' => $male,
            'female_participants' => $female,
            'participants_total' => $total,
            'participants_json' => $participantRows,
        ]);
        $wasResubmit = $lakhpatiTechnicalTraining->canBeEditedByMisFieldSubmitter();
        $lakhpatiTechnicalTraining->save();

        if ($wasResubmit) {
            $this->misFieldWorkflow->resubmitForApproval($lakhpatiTechnicalTraining, (int) $user->id);
        }

        return redirect()
            ->route('staff.lakhpati-technical-trainings.dashboard')
            ->with('status', $wasResubmit
                ? 'Technical training session resubmitted for approval.'
                : 'Technical training session updated.');
    }

    public function destroy(Request $request, LakhpatiTechnicalTraining $lakhpatiTechnicalTraining): RedirectResponse
    {
        $user = $request->user();
        abort_unless(MisFieldActivityApproval::submitterCanWithdraw($user, $lakhpatiTechnicalTraining), 403);

        $lakhpatiTechnicalTraining->delete();

        return redirect()
            ->route('staff.lakhpati-technical-trainings.dashboard')
            ->with('status', 'Technical training submission withdrawn.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);

        $query = LakhpatiTechnicalTraining::query()->with(['district:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']);
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $this->applyListFilters($query, $request);

        $rows = $query->orderByDesc('session_date')->orderByDesc('id')->get();

        return WorkshopDashboardCsvExport::lakhpatiTechnicalTrainings(
            $rows,
            'lakhpati-technical-trainings-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportSingle(Request $request, LakhpatiTechnicalTraining $lakhpatiTechnicalTraining): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $lakhpatiTechnicalTraining);

        return WorkshopDashboardCsvExport::lakhpatiTechnicalTrainings(
            collect([$lakhpatiTechnicalTraining]),
            'lakhpati-technical-training-'.$lakhpatiTechnicalTraining->id.'-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function downloadAttachment(
        Request $request,
        LakhpatiTechnicalTraining $lakhpatiTechnicalTraining,
    ): StreamedResponse|BinaryFileResponse {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $lakhpatiTechnicalTraining);

        $index = max(0, (int) $request->query('index', 0));
        $collection = (string) $request->query('collection', 'attendance');
        $items = match ($collection) {
            'photos' => (array) $lakhpatiTechnicalTraining->workshop_photos_json,
            default => (array) $lakhpatiTechnicalTraining->attendance_media_json,
        };
        $media = collect($items)->get($index);
        abort_if(! is_array($media), 404);

        $path = (string) ($media['path'] ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::exists($path), 404);

        $filename = (string) ($media['original_name'] ?? basename($path));
        $mime = (string) ($media['mime'] ?? '');
        $inline = $request->boolean('inline') && $this->canServeInlineAttendanceMedia($mime, $filename);

        if ($inline) {
            return response()->file(Storage::path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::download($path, $filename);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvedGramPanchayatId(array $validated): ?int
    {
        if (! Schema::hasTable('gram_panchayats')) {
            return null;
        }

        $gpId = (int) ($validated['gram_panchayat_id'] ?? 0);

        return $gpId > 0 ? $gpId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, ?string $existingSessionDate = null): array
    {
        $agencyKeys = array_keys(LakhpatiTechnicalTraining::AGENCY_TYPES);

        $rules = array_merge([
            'session_date' => $existingSessionDate !== null
                ? TodayOnlyDate::rulesAllowingExisting($existingSessionDate)
                : TodayOnlyDate::rules(),
            'district_block_id' => ['required', 'integer', 'exists:district_blocks,id'],
            'area' => ['required', 'string', 'max:191'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'requesting_agency_type' => ['required', 'string', 'in:'.implode(',', $agencyKeys)],
            'session_title' => ['required', 'string', 'max:191'],
            'session_brief' => ['nullable', 'string', 'max:5000'],
            'male_participants' => ['nullable', 'integer', 'min:0'],
            'female_participants' => ['nullable', 'integer', 'min:0'],
            'attendance_media' => ['nullable', 'array', 'max:25'],
            'attendance_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:51200'],
            'workshop_photos' => ['nullable', 'array', 'max:5'],
            'workshop_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ], $this->workshopParticipantValidationRules());

        if (Schema::hasTable('gram_panchayats')) {
            $rules['gram_panchayat_id'] = ['nullable', 'integer', 'exists:gram_panchayats,id'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LakhpatiTechnicalTraining>  $query
     */
    private function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('session_title', 'like', $like)
                    ->orWhere('block', 'like', $like);
            });
        }

        if ($request->filled('agency')) {
            $query->where('requesting_agency_type', (string) $request->query('agency'));
        }

        if ($request->filled('from')) {
            $query->whereDate('session_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('session_date', '<=', (string) $request->query('to'));
        }

        $this->applyEventPeriodFilter($query, $request);
    }

    /**
     * @return array<string, string>
     */
    private function workshopPhotosUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ((array) $request->file('workshop_photos', []) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['workshop_photos.'.$index] = $this->describeFailedUpload($file);
        }

        return $errors;
    }

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedPhotos(array $files): array
    {
        $photoItems = [];

        foreach ($files as $photo) {
            if (! $photo) {
                continue;
            }

            $photoPath = $photo->store('lakhpati-technical-training-photos');
            $mime = (string) ($photo->getClientMimeType() ?? '');
            $photoItems[] = [
                'path' => $photoPath,
                'original_name' => (string) $photo->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($photo->getSize() ?? 0),
                'type' => 'image',
            ];
        }

        return $photoItems;
    }

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedMedia(array $files): array
    {
        $mediaItems = [];

        foreach ($files as $media) {
            if (! $media) {
                continue;
            }

            $mediaPath = $media->store('lakhpati-technical-training-media');
            $mime = (string) ($media->getClientMimeType() ?? '');
            $type = Str::startsWith($mime, 'image/')
                ? 'image'
                : (Str::startsWith($mime, 'video/') ? 'video' : 'document');
            $mediaItems[] = [
                'path' => $mediaPath,
                'original_name' => (string) $media->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($media->getSize() ?? 0),
                'type' => $type,
            ];
        }

        return $mediaItems;
    }

    private function canServeInlineAttendanceMedia(string $mime, string $filename): bool
    {
        if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/')) {
            return true;
        }

        return $mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf');
    }

    /**
     * @return array{event_year: string, event_month: string}
     */
    private function applyEventPeriodFilter($query, Request $request): array
    {
        $eventYear = $request->filled('event_year') ? (int) $request->query('event_year') : null;
        $eventMonth = $request->filled('event_month') ? max(1, min(12, (int) $request->query('event_month'))) : null;

        if ($eventYear !== null && $eventMonth !== null) {
            $query->whereYear('session_date', $eventYear)->whereMonth('session_date', $eventMonth);
        } elseif ($eventYear !== null) {
            $query->whereYear('session_date', $eventYear);
        } elseif ($eventMonth !== null) {
            $query->whereMonth('session_date', $eventMonth);
        }

        return [
            'event_year' => $eventYear !== null ? (string) $eventYear : '',
            'event_month' => $eventMonth !== null ? (string) $eventMonth : '',
        ];
    }

    /**
     * @return list<int>
     */
    private function yearOptions(): array
    {
        return range((int) now()->year + 1, 2000);
    }

    /**
     * @return Collection<int, string>
     */
    private function monthOptions(int $calendarYear): Collection
    {
        return collect(range(1, 12))->mapWithKeys(
            fn (int $month): array => [$month => now()->setDate($calendarYear, $month, 1)->format('F')]
        );
    }

    private function exportRouteForRole(string $role): string
    {
        return match ($role) {
            'state_admin' => 'admin.lakhpati-technical-trainings.export',
            'state_staff' => 'spoc.lakhpati-technical-trainings.export',
            default => 'staff.lakhpati-technical-trainings.export',
        };
    }

    private function canSubmit(string $role): bool
    {
        return $role === 'district_staff';
    }

    private function canViewDashboard(string $role): bool
    {
        return in_array($role, ['district_staff', 'state_staff', 'state_admin'], true);
    }

    private function assertCanEdit(LakhpatiTechnicalTraining $row, int $userId): void
    {
        $user = request()->user();
        abort_unless($user && MisFieldActivityApproval::submitterCanEdit($user, $row), 403);
        abort_unless((int) $row->submitted_by_user_id === $userId, 403);
    }

    private function assertCanAccessRecord(string $role, int $userDistrictId, LakhpatiTechnicalTraining $row): void
    {
        if ($role === 'state_admin' || $role === 'state_staff') {
            return;
        }

        if ($role === 'district_staff' && (int) ($row->district_id ?: 0) === $userDistrictId) {
            return;
        }

        abort(403);
    }
}
