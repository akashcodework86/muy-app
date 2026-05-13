<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\DistrictBlock;
use App\Models\TrainingPackage;
use App\Services\LegacyApplicationServiceCaseSupport;
use App\Services\TrainingPackageMonthSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingPackageAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    private function extraSessionsEnabled(): bool
    {
        return (bool) config('features.training_package_extra_sessions', false);
    }

    public function __construct(
        private TrainingPackageMonthSessionService $monthSessions,
        private LegacyApplicationServiceCaseSupport $legacyApplications,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        $districtId = (int) ($user->district_id ?: 0);
        $blocks = $districtId > 0 ? DistrictBlock::orderedNamesForDistrict($districtId) : [];
        $incubatees = $this->onboardedIncubateesForDistrict($districtId, trim((string) $request->query('q', '')))
            ->values();
        $totalOnboardedCount = $this->onboardedIncubateesCountForDistrict($districtId);

        if (! Schema::hasTable('training_packages')) {
            return view('staff.training-packages.form', array_merge(
                $this->monthPlanViewData($districtId, $request),
                [
                    'user' => $user,
                    'blocks' => $blocks,
                    'incubatees' => $incubatees,
                    'totalOnboardedCount' => $totalOnboardedCount,
                    'migrationMissing' => true,
                ]
            ));
        }

        return view('staff.training-packages.form', array_merge(
            $this->monthPlanViewData($districtId, $request),
            [
                'user' => $user,
                'blocks' => $blocks,
                'incubatees' => $incubatees,
                'totalOnboardedCount' => $totalOnboardedCount,
                'migrationMissing' => false,
            ]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        if (! Schema::hasTable('training_packages')) {
            return redirect()
                ->route('staff.training-packages.create')
                ->withErrors(['training_packages' => 'Training packages table is missing. Please run migrations first.']);
        }

        $rules = array_merge([
            'session_date' => ['required', 'date'],
            'training_batch_name' => ['nullable', 'string', 'max:191'],
            'training_packages' => ['required', 'array', 'min:1'],
            'training_packages.*' => ['required', Rule::in(['t1', 't2', 't3', 't4'])],
            'selected_incubatees' => ['required', 'array', 'min:1'],
            'selected_incubatees.*' => ['integer', 'not_in:0'],
        ], $this->attendanceMediaValidationRules());

        $monthPlanningEnabled = Schema::hasTable('training_package_month_sessions');

        if ($monthPlanningEnabled) {
            $allowedSessionModes = $this->extraSessionsEnabled()
                ? ['planned', 'extra']
                : ['planned'];
            $rules['session_mode'] = ['required', Rule::in($allowedSessionModes)];
            $rules['plan_year'] = ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)];
            $rules['plan_month'] = ['required', 'integer', 'min:1', 'max:12'];

            if ($this->extraSessionsEnabled() && $request->input('session_mode') === 'extra') {
                $rules['extra_session_name'] = ['required', 'string', 'max:191'];
                $rules['month_session_id'] = ['nullable', 'prohibited'];
            } else {
                $rules['month_session_id'] = ['required', 'integer', 'min:1'];
            }
        }

        if ($uploadErrors = $this->attendanceMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate($rules);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required to submit attendance.');

        $monthSession = null;
        $sessionMode = (string) ($validated['session_mode'] ?? 'planned');
        if ($monthPlanningEnabled) {
            if ($sessionMode === 'planned') {
                $monthSession = $this->monthSessions->findOpenSlotForDistrict((int) $validated['month_session_id'], $districtId);
                if (! $monthSession) {
                    return back()
                        ->withInput()
                        ->withErrors(['month_session_id' => 'Selected session is unavailable or already filled for your district.']);
                }

                try {
                    $this->monthSessions->assertSessionDateMatchesSlot($monthSession, (string) $validated['session_date']);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    return back()->withInput()->withErrors($exception->errors());
                }
            }
        }

        $selectedIds = $this->normalizeSelectedIncubateeIds((array) $validated['selected_incubatees']);
        $snapshots = $this->snapshotsForSelectedIncubatees($districtId, $selectedIds);

        if ($snapshots->isEmpty() || $snapshots->count() !== $selectedIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['selected_incubatees' => 'One or more selected incubatees are invalid for your district.']);
        }

        $selectedModules = collect((array) $validated['training_packages'])
            ->map(fn ($m): string => strtolower(trim((string) $m)))
            ->filter(fn (string $m): bool => in_array($m, ['t1', 't2', 't3', 't4'], true))
            ->unique()
            ->values();

        $mediaItems = [];
        foreach ((array) $request->file('attendance_media', []) as $media) {
            if (! $media) {
                continue;
            }
            $mediaPath = $media->store('training-package-attendance-media', 'public');
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

        $attributes = [
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'event_date' => $validated['session_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'month_session_id' => $monthSession?->id,
            'block' => null,
            'training_batch_name' => trim((string) ($validated['training_batch_name'] ?? '')) ?: null,
            'training_packages' => $selectedModules->all(),
            'attendance_file_path' => null,
            'attendance_file_name' => null,
            'attendance_file_mime' => null,
            'attendance_file_size_bytes' => null,
            'attendance_media_json' => $mediaItems,
            'selected_incubatee_ids' => $selectedIds->all(),
            'selected_incubatees_snapshot' => $snapshots->all(),
        ];
        $this->mergeLegacyTrainingPackageColumn($attributes, $selectedModules);

        DB::transaction(function () use ($monthPlanningEnabled, $sessionMode, $districtId, $validated, $user, &$monthSession, $attributes): void {
            if ($monthPlanningEnabled && $sessionMode === 'extra') {
                $monthSession = $this->monthSessions->createExtraSlotForDistrictMonth(
                    $districtId,
                    (int) $validated['plan_year'],
                    (int) $validated['plan_month'],
                    (string) $validated['extra_session_name'],
                    (int) $user->id,
                    (string) $validated['session_date'],
                );
            }

            $attributes['month_session_id'] = $monthSession?->id;
            TrainingPackage::query()->create($attributes);
        });

        return redirect()
            ->route('staff.training-packages.dashboard')
            ->with('status', 'Training package attendance submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard($user->role), 403);

        if (! Schema::hasTable('training_packages')) {
            return view('staff.training-packages.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
            ]);
        }

        $query = TrainingPackage::query()->with(['district:id,name', 'submitter:id,name', 'monthSession:id,session_name,is_extra']);

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
                    ->orWhere('training_batch_name', 'like', $like);
            });
        }

        $package = $this->applyTrainingPackageFilter($query, $request);

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $eventPeriod = $this->applyEventPeriodFilter($query, $request);

        $rows = $query
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $filterYear = (int) ($eventPeriod['event_year'] !== '' ? $eventPeriod['event_year'] : now()->year);

        return view('staff.training-packages.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'currentRole' => (string) $user->role,
            'yearOptions' => $this->trainingPackageYearOptions(),
            'monthOptions' => $this->trainingPackageMonthOptions($filterYear),
            'filters' => [
                'q' => $search,
                'training_package' => $package,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'event_year' => $eventPeriod['event_year'],
                'event_month' => $eventPeriod['event_month'],
            ],
        ]);
    }

    public function show(Request $request, TrainingPackage $trainingPackage): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $trainingPackage);

        $trainingPackage->loadMissing(['district:id,name', 'submitter:id,name', 'monthSession:id,session_name,is_extra']);

        return view('staff.training-packages.show', [
            'row' => $trainingPackage,
            'applicantSnapshots' => $this->enrichedApplicantSnapshots($trainingPackage),
            'currentRole' => (string) $user->role,
            'canEdit' => (string) $user->role === 'district_staff'
                && (int) $trainingPackage->submitted_by_user_id === (int) $user->id,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);

        $query = TrainingPackage::query()->with(['district:id,name', 'submitter:id,name', 'monthSession:id,session_name,is_extra']);
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
                    ->orWhere('training_batch_name', 'like', $like);
            });
        }

        $this->applyTrainingPackageFilter($query, $request);

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $this->applyEventPeriodFilter($query, $request);

        $rows = $query->orderByDesc('event_date')->orderByDesc('id')->get();

        return $this->streamExportCsv(
            $rows,
            'training-packages-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportSingle(Request $request, TrainingPackage $trainingPackage): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $trainingPackage);

        return $this->streamExportCsv(
            collect([$trainingPackage]),
            'training-package-'.$trainingPackage->id.'-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function edit(Request $request, TrainingPackage $trainingPackage): View
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($trainingPackage, (int) $user->id);

        $blocks = DistrictBlock::orderedNamesForDistrict((int) ($trainingPackage->district_id ?: 0));

        $incubatees = $this->onboardedIncubateesForDistrict((int) ($trainingPackage->district_id ?: 0))
            ->values();

        $trainingPackage->loadMissing('monthSession');

        return view('staff.training-packages.edit', [
            'user' => $user,
            'row' => $trainingPackage,
            'blocks' => $blocks,
            'incubatees' => $incubatees,
            'totalOnboardedCount' => $this->onboardedIncubateesCountForDistrict((int) ($trainingPackage->district_id ?: 0)),
            'selectedIds' => collect((array) $trainingPackage->selected_incubatee_ids)->map(fn ($id): int => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, TrainingPackage $trainingPackage): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($trainingPackage, (int) $user->id);

        if ($uploadErrors = $this->attendanceMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate(array_merge([
            'session_date' => ['required', 'date'],
            'training_batch_name' => ['nullable', 'string', 'max:191'],
            'training_packages' => ['required', 'array', 'min:1'],
            'training_packages.*' => ['required', Rule::in(['t1', 't2', 't3', 't4'])],
            'selected_incubatees' => ['required', 'array', 'min:1'],
            'selected_incubatees.*' => ['integer', 'not_in:0'],
        ], $this->attendanceMediaValidationRules()));

        $districtId = (int) ($trainingPackage->district_id ?: 0);
        $selectedIds = $this->normalizeSelectedIncubateeIds((array) $validated['selected_incubatees']);
        $snapshots = $this->snapshotsForSelectedIncubatees($districtId, $selectedIds);

        if ($snapshots->isEmpty() || $snapshots->count() !== $selectedIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['selected_incubatees' => 'One or more selected incubatees are invalid for this district.']);
        }

        $trainingPackage->loadMissing('monthSession');
        if ($trainingPackage->monthSession) {
            try {
                $this->monthSessions->assertSessionDateMatchesSlot($trainingPackage->monthSession, (string) $validated['session_date']);
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return back()->withInput()->withErrors($exception->errors());
            }
        }

        $selectedModules = collect((array) $validated['training_packages'])
            ->map(fn ($m): string => strtolower(trim((string) $m)))
            ->filter(fn (string $m): bool => in_array($m, ['t1', 't2', 't3', 't4'], true))
            ->unique()
            ->values();

        if ($request->hasFile('attendance_media')) {
            $this->deleteExistingMediaFiles($trainingPackage);
            $mediaItems = [];
            foreach ((array) $request->file('attendance_media', []) as $media) {
                if (! $media) {
                    continue;
                }
                $mediaPath = $media->store('training-package-attendance-media', 'public');
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
            $trainingPackage->attendance_media_json = $mediaItems;
        }

        $trainingPackage->event_date = $validated['session_date'];
        $trainingPackage->block = null;
        $trainingPackage->training_batch_name = trim((string) ($validated['training_batch_name'] ?? '')) ?: null;
        $trainingPackage->training_packages = $selectedModules->all();
        if (Schema::hasColumn('training_packages', 'training_package')) {
            $trainingPackage->training_package = (string) ($selectedModules->first() ?? 't1');
        }
        $trainingPackage->selected_incubatee_ids = $selectedIds->all();
        $trainingPackage->selected_incubatees_snapshot = $snapshots->all();
        $trainingPackage->save();

        return redirect()
            ->route('staff.training-packages.dashboard')
            ->with('status', 'Training package attendance updated.');
    }

    public function downloadAttachment(Request $request, TrainingPackage $trainingPackage): StreamedResponse
    {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $trainingPackage);
        $media = collect((array) $trainingPackage->attendance_media_json)->first();
        abort_if(! is_array($media), 404);
        $path = (string) ($media['path'] ?? '');
        abort_if($path === '', 404);

        $disk = Storage::disk('public')->exists($path) ? 'public' : 'local';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, (string) ($media['original_name'] ?? basename($path)));
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

        return $this->onboardedPhase3ApplicantCountForDistrict($districtId);
    }

    private function onboardedPhase3ApplicantCountForDistrict(int $districtId): int
    {
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
    private function enrichedApplicantSnapshots(TrainingPackage $trainingPackage): array
    {
        $snapshots = collect((array) $trainingPackage->selected_incubatees_snapshot)
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

    private function isLegacyPhase2ApplicantSnapshot(array $snap): bool
    {
        return ($snap['source'] ?? '') === 'legacy_phase2' || (int) ($snap['incubatee_id'] ?? 0) < 0;
    }

    private function snapshotNeedsLegacyDetailEnrichment(array $snap): bool
    {
        if ($this->isLegacyPhase2ApplicantSnapshot($snap)) {
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
    private function trainingPackageYearOptions(): array
    {
        return range((int) now()->year + 1, 2000);
    }

    /**
     * @return Collection<int, string>
     */
    private function trainingPackageMonthOptions(int $calendarYear): Collection
    {
        return collect(range(1, 12))->mapWithKeys(
            fn (int $month): array => [$month => now()->setDate($calendarYear, $month, 1)->format('F')]
        );
    }

    private function streamExportCsv(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Entry ID',
            'Date of Session',
            'Session Taken By',
            'District',
            'Training Batch',
            'Training Modules',
            'Uploaded Media Count',
            'Selected Applicants Count',
            'Created At',
            'Updated At',
            'Applicant Incubatee ID',
            'Applicant Name',
            'Applicant Application No',
            'Applicant Phone',
            'Applicant Gender',
            'Applicant Village',
            'Applicant Block',
            'Applicant Onboarding Batch ID',
            'Applicant Onboarding Batch Name',
        ];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                $entry = $row instanceof TrainingPackage ? $row : null;
                if (! $entry) {
                    continue;
                }

                $base = [
                    (string) $entry->id,
                    (string) ($entry->event_date?->format('Y-m-d') ?? ''),
                    (string) $entry->submitted_by_name,
                    (string) ($entry->district_name ?: ($entry->district?->name ?? '')),
                    (string) ($entry->training_batch_name ?? ''),
                    strtoupper(implode(', ', (array) ($entry->training_packages ?? [$entry->training_package]))),
                    (string) count((array) $entry->attendance_media_json),
                    (string) (is_array($entry->selected_incubatee_ids) ? count($entry->selected_incubatee_ids) : 0),
                    (string) ($entry->created_at?->format('Y-m-d H:i:s') ?? ''),
                    (string) ($entry->updated_at?->format('Y-m-d H:i:s') ?? ''),
                ];

                $snapshots = collect((array) $entry->selected_incubatees_snapshot);
                if ($snapshots->isEmpty()) {
                    fputcsv($out, array_merge($base, array_fill(0, 9, '')));
                    continue;
                }

                foreach ($snapshots as $snap) {
                    $snap = is_array($snap) ? $snap : [];
                    fputcsv($out, array_merge($base, [
                        (string) ($snap['incubatee_id'] ?? ''),
                        (string) ($snap['name'] ?? ''),
                        (string) ($snap['application_no'] ?? ''),
                        (string) ($snap['phone'] ?? ''),
                        (string) ($snap['gender'] ?? ''),
                        (string) ($snap['village'] ?? ''),
                        (string) ($snap['block_name'] ?? ''),
                        (string) ($snap['onboarding_batch_id'] ?? ''),
                        (string) ($snap['onboarding_batch_name'] ?? ''),
                    ]));
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function deleteExistingMediaFiles(TrainingPackage $trainingPackage): void
    {
        foreach ((array) $trainingPackage->attendance_media_json as $media) {
            if (! is_array($media)) {
                continue;
            }
            $path = (string) ($media['path'] ?? '');
            if ($path === '') {
                continue;
            }
            foreach (['public', 'local'] as $disk) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }
    }

    private function mergeLegacyTrainingPackageColumn(array &$attributes, Collection $selectedModules): void
    {
        if (! Schema::hasColumn('training_packages', 'training_package')) {
            return;
        }

        $attributes['training_package'] = (string) ($selectedModules->first() ?? 't1');
    }

    private function applyTrainingPackageFilter($query, Request $request): string
    {
        $package = trim((string) $request->query('training_package', ''));
        if (! in_array($package, ['t1', 't2', 't3', 't4'], true)) {
            return '';
        }

        $hasLegacyColumn = Schema::hasColumn('training_packages', 'training_package');
        $query->where(function ($q) use ($package, $hasLegacyColumn): void {
            if ($hasLegacyColumn) {
                $q->where('training_package', $package);
            }

            if ($hasLegacyColumn) {
                $q->orWhereJsonContains('training_packages', $package);
            } else {
                $q->whereJsonContains('training_packages', $package);
            }
        });

        return $package;
    }

    /**
     * @return array{
     *     monthPlanningEnabled:bool,
     *     planYear:int,
     *     planMonth:int,
     *     monthSlots:\Illuminate\Support\Collection,
     *     monthExtraSlots:\Illuminate\Support\Collection,
     *     monthSummary:array{required:int,filled:int,remaining:int,extra_filled:int},
     *     monthOptions:\Illuminate\Support\Collection
     * }
     */
    private function monthPlanViewData(int $districtId, Request $request): array
    {
        $monthPlanningEnabled = Schema::hasTable('training_package_month_sessions');
        $planYear = (int) $request->query('plan_year', now()->year);
        $planMonth = max(1, min(12, (int) $request->query('plan_month', now()->month)));

        $monthSlots = $monthPlanningEnabled
            ? $this->monthSessions->slotsForDistrictMonth($districtId, $planYear, $planMonth)
            : collect();

        $monthExtraSlots = $monthPlanningEnabled
            ? $this->monthSessions->extraSlotsForDistrictMonth($districtId, $planYear, $planMonth)
            : collect();

        $monthSummary = $monthPlanningEnabled
            ? $this->monthSessions->districtMonthSummary($districtId, $planYear, $planMonth)
            : ['required' => 0, 'filled' => 0, 'remaining' => 0, 'extra_filled' => 0];

        $monthOptions = collect(range(1, 12))->mapWithKeys(fn (int $month): array => [
            $month => now()->setDate($planYear, $month, 1)->format('F'),
        ]);

        return [
            'monthPlanningEnabled' => $monthPlanningEnabled,
            'extraSessionsEnabled' => $this->extraSessionsEnabled(),
            'planYear' => $planYear,
            'planMonth' => $planMonth,
            'monthSlots' => $monthSlots,
            'monthExtraSlots' => $monthExtraSlots,
            'monthSummary' => $monthSummary,
            'monthOptions' => $monthOptions,
        ];
    }

    private function canSubmit(string $role): bool
    {
        return $role === 'district_staff';
    }

    private function canViewDashboard(string $role): bool
    {
        return in_array($role, ['district_staff', 'state_staff', 'state_admin'], true);
    }

    private function assertCanEdit(TrainingPackage $row, int $userId): void
    {
        abort_unless((int) $row->submitted_by_user_id === $userId, 403);
    }

    private function assertCanAccessRecord(string $role, int $userDistrictId, TrainingPackage $row): void
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
