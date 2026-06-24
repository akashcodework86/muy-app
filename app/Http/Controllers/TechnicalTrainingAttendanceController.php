<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\TechnicalTraining;
use App\Support\IncubateeAttendeeCounts;
use App\Support\MisFieldActivityApproval;
use App\Support\WorkshopDashboardCsvExport;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\MisFieldActivityWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalTrainingAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function __construct(
        private LegacyApplicationServiceCaseSupport $legacyApplications,
        private MisFieldActivityWorkflowService $misFieldWorkflow,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $incubatees = $this->onboardedIncubateesForDistrict($districtId, trim((string) $request->query('q', '')))
            ->values();

        return view('staff.technical-trainings.form', [
            'user' => $user,
            'incubatees' => $incubatees,
            'totalOnboardedCount' => $this->onboardedIncubateesCountForDistrict($districtId),
            'migrationMissing' => ! Schema::hasTable('technical_trainings'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        if (! Schema::hasTable('technical_trainings')) {
            return redirect()
                ->route('staff.technical-trainings.create')
                ->withErrors(['session_name' => 'Technical trainings table is missing. Please run migrations first.']);
        }

        if ($uploadErrors = $this->attendanceMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate(array_merge([
            'session_date' => ['required', 'date'],
            'session_name' => ['required', 'string', 'max:191'],
            'session_brief' => ['nullable', 'string', 'max:5000'],
            'training_batch_name' => ['nullable', 'string', 'max:191'],
            'selected_incubatees' => ['required', 'array', 'min:1'],
            'selected_incubatees.*' => ['integer', 'not_in:0'],
        ], $this->attendanceMediaValidationRules(), [
            'attendance_media' => ['nullable', 'array', 'max:25'],
        ]));

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required to submit attendance.');

        $selectedIds = $this->normalizeSelectedIncubateeIds((array) $validated['selected_incubatees']);
        $snapshots = $this->snapshotsForSelectedIncubatees($districtId, $selectedIds);

        if ($snapshots->isEmpty() || $snapshots->count() !== $selectedIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['selected_incubatees' => 'One or more selected incubatees are invalid for your district.']);
        }

        $mediaItems = $this->storeUploadedMedia((array) $request->file('attendance_media', []));

        $training = TechnicalTraining::query()->create([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'event_date' => $validated['session_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'training_batch_name' => trim((string) ($validated['training_batch_name'] ?? '')) ?: null,
            'session_name' => trim((string) $validated['session_name']),
            'session_brief' => trim((string) ($validated['session_brief'] ?? '')) ?: null,
            'attendance_media_json' => $mediaItems,
            'selected_incubatee_ids' => $selectedIds->all(),
            'selected_incubatees_snapshot' => $snapshots->all(),
        ]);

        $this->misFieldWorkflow->submitForApproval($training, (int) $user->id);

        return redirect()
            ->route('staff.technical-trainings.dashboard')
            ->with('status', 'Technical training attendance submitted for approval.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard($user->role), 403);

        if (! Schema::hasTable('technical_trainings')) {
            return view('staff.technical-trainings.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
            ]);
        }

        $query = TechnicalTraining::query()->with(['district:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']);

        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('training_batch_name', 'like', $like)
                    ->orWhere('session_name', 'like', $like)
                    ->orWhere('session_brief', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $eventPeriod = $this->applyEventPeriodFilter($query, $request);

        $totals = IncubateeAttendeeCounts::sumForRecords(
            (clone $query)->get(['selected_incubatee_ids', 'selected_incubatees_snapshot'])
        );

        $rows = $query
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $filterYear = (int) ($eventPeriod['event_year'] !== '' ? $eventPeriod['event_year'] : now()->year);

        return view('staff.technical-trainings.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'totals' => $totals,
            'currentRole' => (string) $user->role,
            'yearOptions' => $this->yearOptions(),
            'monthOptions' => $this->monthOptions($filterYear),
            'filters' => [
                'q' => $search,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'event_year' => $eventPeriod['event_year'],
                'event_month' => $eventPeriod['event_month'],
            ],
            'exportRoute' => $this->exportRouteForRole((string) $user->role),
        ]);
    }

    public function show(Request $request, TechnicalTraining $technicalTraining): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $technicalTraining);

        $technicalTraining->loadMissing(['district:id,name', 'submitter:id,name']);

        return view('staff.technical-trainings.show', [
            'row' => $technicalTraining,
            'applicantSnapshots' => $this->enrichedApplicantSnapshots($technicalTraining),
            'currentRole' => (string) $user->role,
            'canEdit' => MisFieldActivityApproval::submitterCanEdit($user, $technicalTraining),
            'canWithdraw' => MisFieldActivityApproval::submitterCanWithdraw($user, $technicalTraining),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);

        $query = TechnicalTraining::query()->with(['district:id,name', 'submitter:id,name', 'misFieldSpoc:id,name']);
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('training_batch_name', 'like', $like)
                    ->orWhere('session_name', 'like', $like)
                    ->orWhere('session_brief', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $this->applyEventPeriodFilter($query, $request);

        $rows = $query->orderByDesc('event_date')->orderByDesc('id')->get();

        return WorkshopDashboardCsvExport::technicalTrainings(
            $rows,
            'technical-trainings-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportSingle(Request $request, TechnicalTraining $technicalTraining): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $technicalTraining);

        return WorkshopDashboardCsvExport::technicalTrainings(
            collect([$technicalTraining]),
            'technical-training-'.$technicalTraining->id.'-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function edit(Request $request, TechnicalTraining $technicalTraining): View
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($technicalTraining, (int) $user->id);

        $incubatees = $this->onboardedIncubateesForDistrict((int) ($technicalTraining->district_id ?: 0))
            ->values();

        return view('staff.technical-trainings.edit', [
            'user' => $user,
            'row' => $technicalTraining,
            'incubatees' => $incubatees,
            'totalOnboardedCount' => $this->onboardedIncubateesCountForDistrict((int) ($technicalTraining->district_id ?: 0)),
            'selectedIds' => collect((array) $technicalTraining->selected_incubatee_ids)->map(fn ($id): int => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, TechnicalTraining $technicalTraining): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($technicalTraining, (int) $user->id);

        if ($uploadErrors = $this->attendanceMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate(array_merge([
            'session_date' => ['required', 'date'],
            'session_name' => ['required', 'string', 'max:191'],
            'session_brief' => ['nullable', 'string', 'max:5000'],
            'training_batch_name' => ['nullable', 'string', 'max:191'],
            'selected_incubatees' => ['required', 'array', 'min:1'],
            'selected_incubatees.*' => ['integer', 'not_in:0'],
        ], $this->attendanceMediaValidationRules(), [
            'attendance_media' => ['nullable', 'array', 'max:25'],
        ]));

        $districtId = (int) ($technicalTraining->district_id ?: 0);
        $selectedIds = $this->normalizeSelectedIncubateeIds((array) $validated['selected_incubatees']);
        $snapshots = $this->snapshotsForSelectedIncubatees($districtId, $selectedIds);

        if ($snapshots->isEmpty() || $snapshots->count() !== $selectedIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['selected_incubatees' => 'One or more selected incubatees are invalid for this district.']);
        }

        $newUploads = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($newUploads !== []) {
            $existingMedia = collect((array) $technicalTraining->attendance_media_json)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
            $combinedCount = $existingMedia->count() + count($newUploads);
            abort_if($combinedCount > 25, 422, 'You can upload up to 25 files per session.');

            $technicalTraining->attendance_media_json = $existingMedia
                ->merge($this->storeUploadedMedia($newUploads))
                ->values()
                ->all();
        }

        $technicalTraining->event_date = $validated['session_date'];
        $technicalTraining->training_batch_name = trim((string) ($validated['training_batch_name'] ?? '')) ?: null;
        $technicalTraining->session_name = trim((string) $validated['session_name']);
        $technicalTraining->session_brief = trim((string) ($validated['session_brief'] ?? '')) ?: null;
        $technicalTraining->selected_incubatee_ids = $selectedIds->all();
        $technicalTraining->selected_incubatees_snapshot = $snapshots->all();
        $wasResubmit = $technicalTraining->canBeEditedByMisFieldSubmitter();
        $technicalTraining->save();

        if ($wasResubmit) {
            $this->misFieldWorkflow->resubmitForApproval($technicalTraining, (int) $user->id);
        }

        return redirect()
            ->route('staff.technical-trainings.dashboard')
            ->with('status', $wasResubmit
                ? 'Technical training resubmitted for approval.'
                : 'Technical training attendance updated.');
    }

    public function destroy(Request $request, TechnicalTraining $technicalTraining): RedirectResponse
    {
        $user = $request->user();
        abort_unless(MisFieldActivityApproval::submitterCanWithdraw($user, $technicalTraining), 403);

        $this->deleteExistingMediaFiles($technicalTraining);
        $technicalTraining->delete();

        return redirect()
            ->route('staff.technical-trainings.dashboard')
            ->with('status', 'Technical training submission withdrawn.');
    }

    public function downloadAttachment(Request $request, TechnicalTraining $technicalTraining): StreamedResponse|BinaryFileResponse
    {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $technicalTraining);

        $index = max(0, (int) $request->query('index', 0));
        $media = collect((array) $technicalTraining->attendance_media_json)->get($index);
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

    private function canServeInlineAttendanceMedia(string $mime, string $filename): bool
    {
        if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/')) {
            return true;
        }

        if ($mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<\Illuminate\Http\UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedMedia(array $files): array
    {
        $mediaItems = [];

        foreach ($files as $media) {
            if (! $media) {
                continue;
            }

            $mediaPath = $media->store('technical-training-attendance-media');
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

    private function onboardedIncubateesForDistrict(int $districtId, string $search = ''): Collection
    {
        if ($districtId <= 0) {
            return collect();
        }

        return $this->onboardedPhase3ApplicantsForDistrict($districtId, $search)
            ->sortBy(fn (array $row): string => mb_strtolower((string) ($row['name'] ?? '')))
            ->values();
    }

    private function onboardedPhase3ApplicantsForDistrict(int $districtId, string $search = ''): Collection
    {
        $payloadValue = fn (string $path): string => DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract(cs.payload, '$.{$path}')"
            : "JSON_UNQUOTE(JSON_EXTRACT(cs.payload, '$.{$path}'))";

        $query = DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('cs.district_id', $districtId)
            ->selectRaw("
                cs.id as incubatee_id,
                cs.applicant_name as name,
                cs.application_no as application_no,
                cs.phone as phone,
                {$payloadValue('gender')} as gender,
                {$payloadValue('village')} as village,
                {$payloadValue('block')} as block_name,
                ob.id as onboarding_batch_id,
                ob.name as onboarding_batch_name
            ");

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('cs.applicant_name', 'like', $like)
                    ->orWhere('cs.application_no', 'like', $like)
                    ->orWhere('cs.phone', 'like', $like);
            });
        }

        return $query
            ->orderByDesc('obc.created_at')
            ->get()
            ->map(fn ($row): array => [
                'incubatee_id' => (int) $row->incubatee_id,
                'source' => 'phase3',
                'name' => (string) ($row->name ?? ''),
                'application_no' => (string) ($row->application_no ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'gender' => (string) ($row->gender ?? ''),
                'village' => (string) ($row->village ?? ''),
                'block_name' => (string) ($row->block_name ?? ''),
                'onboarding_batch_id' => (int) ($row->onboarding_batch_id ?? 0),
                'onboarding_batch_name' => (string) ($row->onboarding_batch_name ?? ''),
            ])
            ->unique('incubatee_id')
            ->values();
    }

    private function onboardedIncubateesCountForDistrict(int $districtId): int
    {
        if ($districtId <= 0) {
            return 0;
        }

        return (int) DB::table('onboarding_batch_cfa as obc')
            ->join('onboarding_batches as ob', 'ob.id', '=', 'obc.onboarding_batch_id')
            ->join('cfa_submissions as cs', 'cs.id', '=', 'obc.cfa_submission_id')
            ->where('ob.status', 'locked')
            ->whereNotNull('ob.locked_at')
            ->where('cs.district_id', $districtId)
            ->distinct('cs.id')
            ->count('cs.id');
    }

    /**
     * @param  list<int|string>  $rawIds
     */
    private function normalizeSelectedIncubateeIds(array $rawIds): Collection
    {
        return collect($rawIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== 0)
            ->unique()
            ->values();
    }

    private function snapshotsForSelectedIncubatees(int $districtId, Collection $selectedIds): Collection
    {
        $snapshotMap = $this->onboardedIncubateesForDistrict($districtId)
            ->keyBy(fn (array $row): int => (int) $row['incubatee_id']);

        return $selectedIds
            ->map(fn (int $id): ?array => $snapshotMap->get($id))
            ->filter()
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enrichedApplicantSnapshots(TechnicalTraining $technicalTraining): array
    {
        $snapshots = collect((array) $technicalTraining->selected_incubatees_snapshot)
            ->map(fn ($snap): array => is_array($snap) ? $snap : [])
            ->values();

        $legacyApplicationIds = [];
        $legacyApplicationNumbers = [];

        foreach ($snapshots as $snap) {
            if (! $this->snapshotNeedsLegacyDetailEnrichment($snap)) {
                continue;
            }

            $legacyApplicationId = $this->legacyApplicationIdFromSnapshot($snap);
            if ($legacyApplicationId > 0) {
                $legacyApplicationIds[] = $legacyApplicationId;
            }

            $incubateeId = (int) ($snap['incubatee_id'] ?? 0);
            if ($incubateeId > 0) {
                $legacyApplicationIds[] = $incubateeId;
            }

            $applicationNumber = trim((string) ($snap['application_no'] ?? ''));
            if ($applicationNumber !== '') {
                $legacyApplicationNumbers[] = $applicationNumber;
            }
        }

        $legacyDetailsById = $this->legacyApplications->applicantSnapshotsByLegacyApplicationIds($legacyApplicationIds);
        $legacyDetailsByApplicationNumber = $this->legacyApplications->applicantSnapshotsByLegacyApplicationNumbers($legacyApplicationNumbers);

        return $snapshots
            ->map(function (array $snap) use ($legacyDetailsById, $legacyDetailsByApplicationNumber): array {
                if (! $this->snapshotNeedsLegacyDetailEnrichment($snap)) {
                    return $snap;
                }

                $details = $this->resolveLegacyApplicantDetails($snap, $legacyDetailsById, $legacyDetailsByApplicationNumber);
                if ($details === null) {
                    return $snap;
                }

                return $this->mergeLegacyApplicantDetailsIntoSnapshot($snap, $details);
            })
            ->all();
    }

    private function snapshotNeedsLegacyDetailEnrichment(array $snap): bool
    {
        if (($snap['source'] ?? '') === 'legacy_phase2' || (int) ($snap['incubatee_id'] ?? 0) < 0) {
            return true;
        }

        foreach (['gender', 'village', 'block_name'] as $field) {
            if (trim((string) ($snap[$field] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private function legacyApplicationIdFromSnapshot(array $snap): int
    {
        $legacyApplicationId = (int) ($snap['legacy_application_id'] ?? 0);
        if ($legacyApplicationId > 0) {
            return $legacyApplicationId;
        }

        $incubateeId = (int) ($snap['incubatee_id'] ?? 0);

        return $incubateeId < 0 ? abs($incubateeId) : 0;
    }

    /**
     * @param  array<int, array<string, string>>  $legacyDetailsById
     * @param  array<string, array<string, mixed>>  $legacyDetailsByApplicationNumber
     * @return array<string, mixed>|null
     */
    private function resolveLegacyApplicantDetails(
        array $snap,
        array $legacyDetailsById,
        array $legacyDetailsByApplicationNumber,
    ): ?array {
        $legacyApplicationId = $this->legacyApplicationIdFromSnapshot($snap);
        if ($legacyApplicationId > 0 && isset($legacyDetailsById[$legacyApplicationId])) {
            return array_merge(
                ['legacy_application_id' => $legacyApplicationId],
                $legacyDetailsById[$legacyApplicationId]
            );
        }

        $incubateeId = (int) ($snap['incubatee_id'] ?? 0);
        if ($incubateeId > 0 && isset($legacyDetailsById[$incubateeId])) {
            return array_merge(
                ['legacy_application_id' => $incubateeId],
                $legacyDetailsById[$incubateeId]
            );
        }

        $applicationNumber = mb_strtolower(trim((string) ($snap['application_no'] ?? '')));
        if ($applicationNumber !== '' && isset($legacyDetailsByApplicationNumber[$applicationNumber])) {
            return $legacyDetailsByApplicationNumber[$applicationNumber];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function mergeLegacyApplicantDetailsIntoSnapshot(array $snap, array $details): array
    {
        foreach (['name', 'application_no', 'phone', 'gender', 'village', 'block_name'] as $field) {
            $detailValue = trim((string) ($details[$field] ?? ''));
            if ($detailValue !== '') {
                $snap[$field] = $detailValue;
            }
        }

        $legacyApplicationId = (int) ($details['legacy_application_id'] ?? 0);
        if ($legacyApplicationId > 0) {
            $snap['legacy_application_id'] = $legacyApplicationId;
            $snap['incubatee_id'] = -1 * $legacyApplicationId;
        }

        $snap['source'] = 'legacy_phase2';

        return $snap;
    }

    /**
     * @return array{event_year: string, event_month: string}
     */
    private function applyEventPeriodFilter($query, Request $request): array
    {
        $eventYear = $request->filled('event_year') ? (int) $request->query('event_year') : null;
        $eventMonth = $request->filled('event_month') ? max(1, min(12, (int) $request->query('event_month'))) : null;

        if ($eventYear !== null && $eventMonth !== null) {
            $query->whereYear('event_date', $eventYear)->whereMonth('event_date', $eventMonth);
        } elseif ($eventYear !== null) {
            $query->whereYear('event_date', $eventYear);
        } elseif ($eventMonth !== null) {
            $query->whereMonth('event_date', $eventMonth);
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

    private function deleteExistingMediaFiles(TechnicalTraining $technicalTraining): void
    {
        foreach ((array) $technicalTraining->attendance_media_json as $media) {
            if (! is_array($media)) {
                continue;
            }
            $path = (string) ($media['path'] ?? '');
            if ($path !== '' && Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

    private function exportRouteForRole(string $role): string
    {
        return match ($role) {
            'state_admin' => 'admin.technical-trainings.export',
            'state_staff' => 'spoc.technical-trainings.export',
            default => 'staff.technical-trainings.export',
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

    private function assertCanEdit(TechnicalTraining $row, int $userId): void
    {
        $user = request()->user();
        abort_unless($user && MisFieldActivityApproval::submitterCanEdit($user, $row), 403);
        abort_unless((int) $row->submitted_by_user_id === $userId, 403);
    }

    private function assertCanAccessRecord(string $role, int $userDistrictId, TechnicalTraining $row): void
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
