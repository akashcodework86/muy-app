<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\District;
use App\Models\Hub;
use App\Models\LineDepartmentMeeting;
use App\Models\User;
use App\Support\LineDepartmentMeetingAccess;
use App\Support\LineDepartmentMeetingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LineDepartmentMeetingController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function create(Request $request): View
    {
        abort_unless(LineDepartmentMeetingAccess::canSubmit($request->user()), 403);

        return view('line-department-meetings.form', $this->formViewData($request, null));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canSubmit($user), 403);

        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        if (! Schema::hasTable('line_department_meetings')) {
            return redirect()
                ->route($prefix.'line-department-meetings.create')
                ->withErrors(['department_name' => 'Table not found. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->proofMediaUploadErrors($request),
            $this->photoUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request, requireProof: true);
        $location = $this->resolveLocation($validated, $user);

        $proofFiles = array_values(array_filter((array) $request->file('proof_media', [])));
        if ($proofFiles === []) {
            return back()->withInput()->withErrors(['proof_media' => 'Upload meeting proof (minutes, letter, or email).']);
        }

        LineDepartmentMeeting::query()->create(array_merge([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'meeting_date' => $validated['meeting_date'],
            'meeting_level' => (string) $validated['meeting_level'],
            'meeting_mode' => (string) $validated['meeting_mode'],
            'venue' => trim((string) ($validated['venue'] ?? '')) ?: null,
            'department_name' => trim((string) $validated['department_name']),
            'department_unit' => trim((string) ($validated['department_unit'] ?? '')) ?: null,
            'official_name' => trim((string) $validated['official_name']),
            'official_designation' => trim((string) $validated['official_designation']),
            'official_phone' => trim((string) ($validated['official_phone'] ?? '')) ?: null,
            'muy_staff_present' => trim((string) $validated['muy_staff_present']),
            'meeting_purpose' => (string) $validated['meeting_purpose'],
            'meeting_purpose_other' => (string) $validated['meeting_purpose'] === 'other'
                ? trim((string) ($validated['meeting_purpose_other'] ?? ''))
                : null,
            'agenda_summary' => trim((string) $validated['agenda_summary']),
            'outcome_decision' => trim((string) $validated['outcome_decision']),
            'incubatees_discussed_json' => $this->normalizeIncubateesDiscussed($request),
            'proof_media_json' => $this->storeUploadedMedia($proofFiles, 'line-department-meeting-proof'),
            'photos_json' => $this->storeOptionalPhotos($request),
        ], $location));

        return redirect()
            ->route($prefix.'line-department-meetings.dashboard')
            ->with('status', 'Line department meeting (MIS 12.2) submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('line_department_meetings')) {
            return view('line-department-meetings.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'totals' => ['meetings' => 0],
                'canSubmit' => LineDepartmentMeetingAccess::canSubmit($user),
            ]);
        }

        $query = LineDepartmentMeeting::query()->with('submitter:id,name');
        $this->scopeDashboardQuery($query, $user);
        $this->applyListFilters($query, $request);

        $totals = ['meetings' => (int) (clone $query)->count()];
        $rows = $query->orderByDesc('meeting_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('line-department-meetings.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'currentRole' => (string) $user->role,
            'canSubmit' => LineDepartmentMeetingAccess::canSubmit($user),
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'level' => (string) $request->query('level', ''),
            ],
            'totals' => $totals,
            'exportRoute' => $this->exportRouteForUser($user),
            'meetingLevels' => LineDepartmentMeeting::MEETING_LEVELS,
        ]);
    }

    public function show(Request $request, LineDepartmentMeeting $ldmMeeting): View
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canViewDashboard($user), 403);
        abort_unless($this->canViewRecord($user, $ldmMeeting), 403);
        $ldmMeeting->loadMissing('submitter:id,name');

        return view('line-department-meetings.show', [
            'row' => $ldmMeeting,
            'currentRole' => (string) $user->role,
            'canEdit' => LineDepartmentMeetingAccess::canEdit($user, $ldmMeeting),
            'canDelete' => LineDepartmentMeetingAccess::canDelete($user, $ldmMeeting),
        ]);
    }

    public function edit(Request $request, LineDepartmentMeeting $ldmMeeting): View
    {
        abort_unless(LineDepartmentMeetingAccess::canEdit($request->user(), $ldmMeeting), 403);

        return view('line-department-meetings.form', $this->formViewData($request, $ldmMeeting));
    }

    public function update(Request $request, LineDepartmentMeeting $ldmMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canEdit($user, $ldmMeeting), 403);
        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        if ($uploadErrors = array_merge(
            $this->proofMediaUploadErrors($request),
            $this->photoUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $hasExistingProof = $ldmMeeting->hasProofDocument();
        $validated = $this->validateSubmission($request, requireProof: ! $hasExistingProof);
        $location = $this->resolveLocation($validated, $user);

        $newProof = array_values(array_filter((array) $request->file('proof_media', [])));
        if ($newProof === [] && ! $hasExistingProof) {
            return back()->withInput()->withErrors(['proof_media' => 'Upload meeting proof.']);
        }
        if ($newProof !== []) {
            $existing = collect((array) $ldmMeeting->proof_media_json)->filter(fn ($item): bool => is_array($item))->values();
            if ($existing->count() + count($newProof) > 5) {
                return back()->withInput()->withErrors(['proof_media' => 'You can upload up to 5 proof files.']);
            }
            $ldmMeeting->proof_media_json = $existing->merge($this->storeUploadedMedia($newProof, 'line-department-meeting-proof'))->values()->all();
        }

        $newPhotos = array_values(array_filter((array) $request->file('photos', [])));
        if ($newPhotos !== []) {
            $existingPhotos = collect((array) $ldmMeeting->photos_json)->filter(fn ($item): bool => is_array($item))->values();
            if ($existingPhotos->count() + count($newPhotos) > 3) {
                return back()->withInput()->withErrors(['photos' => 'You can upload up to 3 photos.']);
            }
            $ldmMeeting->photos_json = $existingPhotos->merge($this->storeUploadedMedia($newPhotos, 'line-department-meeting-photos'))->values()->all();
        }

        $ldmMeeting->fill(array_merge([
            'meeting_date' => $validated['meeting_date'],
            'meeting_level' => (string) $validated['meeting_level'],
            'meeting_mode' => (string) $validated['meeting_mode'],
            'venue' => trim((string) ($validated['venue'] ?? '')) ?: null,
            'department_name' => trim((string) $validated['department_name']),
            'department_unit' => trim((string) ($validated['department_unit'] ?? '')) ?: null,
            'official_name' => trim((string) $validated['official_name']),
            'official_designation' => trim((string) $validated['official_designation']),
            'official_phone' => trim((string) ($validated['official_phone'] ?? '')) ?: null,
            'muy_staff_present' => trim((string) $validated['muy_staff_present']),
            'meeting_purpose' => (string) $validated['meeting_purpose'],
            'meeting_purpose_other' => (string) $validated['meeting_purpose'] === 'other'
                ? trim((string) ($validated['meeting_purpose_other'] ?? ''))
                : null,
            'agenda_summary' => trim((string) $validated['agenda_summary']),
            'outcome_decision' => trim((string) $validated['outcome_decision']),
            'incubatees_discussed_json' => $this->normalizeIncubateesDiscussed($request),
        ], $location));
        $ldmMeeting->save();

        return redirect()->route($prefix.'line-department-meetings.dashboard')->with('status', 'Meeting updated.');
    }

    public function destroy(Request $request, LineDepartmentMeeting $ldmMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canDelete($user, $ldmMeeting), 403);
        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        $this->deleteStoredMediaFiles((array) $ldmMeeting->proof_media_json);
        $this->deleteStoredMediaFiles((array) $ldmMeeting->photos_json);
        $ldmMeeting->delete();

        return redirect()->route($prefix.'line-department-meetings.dashboard')->with('status', 'Meeting deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canViewDashboard($user), 403);

        $query = LineDepartmentMeeting::query();
        $this->scopeDashboardQuery($query, $user);
        $this->applyListFilters($query, $request);
        $rows = $query->orderByDesc('meeting_date')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'Level', 'Department', 'Official', 'Purpose', 'District', 'Hub', 'Entered by']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->meeting_date?->format('Y-m-d') ?? '',
                    $row->meetingLevelLabel(),
                    $row->department_name,
                    $row->official_name,
                    $row->meetingPurposeLabel(),
                    $row->district_name ?? '',
                    $row->hub_name ?? '',
                    $row->submitted_by_name,
                ]);
            }
            fclose($out);
        }, 'line-department-meetings-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadAttachment(
        Request $request,
        LineDepartmentMeeting $ldmMeeting,
    ): StreamedResponse|BinaryFileResponse {
        abort_unless(LineDepartmentMeetingAccess::canViewDashboard($request->user()), 403);
        abort_unless($this->canViewRecord($request->user(), $ldmMeeting), 403);

        $index = max(0, (int) $request->query('index', 0));
        $collection = (string) $request->query('collection', 'proof');
        $items = $collection === 'photos' ? (array) $ldmMeeting->photos_json : (array) $ldmMeeting->proof_media_json;
        $media = collect($items)->get($index);
        abort_if(! is_array($media), 404);

        $path = (string) ($media['path'] ?? '');
        abort_if($path === '' || ! Storage::exists($path), 404);

        $filename = (string) ($media['original_name'] ?? basename($path));
        $mime = (string) ($media['mime'] ?? '');
        if ($request->boolean('inline') && Str::startsWith($mime, 'image/')) {
            return response()->file(Storage::path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::download($path, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(Request $request, ?LineDepartmentMeeting $row): array
    {
        $user = $request->user();
        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        return [
            'user' => $user,
            'row' => $row,
            'migrationMissing' => ! Schema::hasTable('line_department_meetings'),
            'storeRoute' => $row ? $prefix.'line-department-meetings.update' : $prefix.'line-department-meetings.store',
            'dashboardRoute' => $user?->role === 'state_admin'
                ? 'admin.line-department-meetings.dashboard'
                : $prefix.'line-department-meetings.dashboard',
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'districts' => $this->districtsForUser($user),
            'meetingLevels' => LineDepartmentMeeting::MEETING_LEVELS,
            'meetingModes' => LineDepartmentMeetingOptions::meetingModes(),
            'meetingPurposes' => LineDepartmentMeetingOptions::meetingPurposes(),
            'defaultHubId' => (int) ($user?->hub_id ?? 0),
            'defaultDistrictId' => (int) ($user?->district_id ?? 0),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LineDepartmentMeeting>  $query
     */
    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_admin' || LineDepartmentMeetingAccess::isStateStaffSpoc($user)) {
            return;
        }

        if ($user->role === 'hub_admin') {
            $query->where('hub_id', (int) $user->hub_id);

            return;
        }

        if (LineDepartmentMeetingAccess::isIncubationManager($user)) {
            $query->where('district_id', (int) $user->district_id);
        }
    }

    private function canViewRecord(User $user, LineDepartmentMeeting $row): bool
    {
        if ($user->role === 'state_admin' || LineDepartmentMeetingAccess::isStateStaffSpoc($user)) {
            return true;
        }

        if ($user->role === 'hub_admin') {
            return (int) $row->hub_id === (int) $user->hub_id
                || ((int) $row->submitted_by_user_id === (int) $user->id);
        }

        if (LineDepartmentMeetingAccess::isIncubationManager($user)) {
            return (int) $row->district_id === (int) $user->district_id
                || ((int) $row->submitted_by_user_id === (int) $user->id);
        }

        return (int) $row->submitted_by_user_id === (int) $user->id;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LineDepartmentMeeting>  $query
     */
    private function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('department_name', 'like', $like)
                    ->orWhere('official_name', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('agenda_summary', 'like', $like);
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('meeting_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('meeting_date', '<=', (string) $request->query('to'));
        }
        if ($request->filled('level')) {
            $query->where('meeting_level', (string) $request->query('level'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, bool $requireProof = false): array
    {
        $levelKeys = array_keys(LineDepartmentMeeting::MEETING_LEVELS);
        $modeKeys = array_keys(LineDepartmentMeetingOptions::meetingModes());
        $purposeKeys = array_keys(LineDepartmentMeetingOptions::meetingPurposes());

        $rules = [
            'meeting_date' => ['required', 'date'],
            'meeting_level' => ['required', 'string', Rule::in($levelKeys)],
            'hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'meeting_mode' => ['required', 'string', Rule::in($modeKeys)],
            'venue' => ['nullable', 'string', 'max:191'],
            'department_name' => ['required', 'string', 'max:191'],
            'department_unit' => ['nullable', 'string', 'max:191'],
            'official_name' => ['required', 'string', 'max:191'],
            'official_designation' => ['required', 'string', 'max:191'],
            'official_phone' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
            'muy_staff_present' => ['required', 'string', 'max:5000'],
            'meeting_purpose' => ['required', 'string', Rule::in($purposeKeys)],
            'meeting_purpose_other' => ['nullable', 'string', 'max:191', 'required_if:meeting_purpose,other'],
            'agenda_summary' => ['required', 'string', 'max:5000'],
            'outcome_decision' => ['required', 'string', 'max:5000'],
            'incubatees_discussed' => ['nullable', 'string', 'max:5000'],
            'proof_media' => [$requireProof ? 'required' : 'nullable', 'array', 'max:5'],
            'proof_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:51200'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request): void {
            $level = (string) $request->input('meeting_level', '');
            if (in_array($level, ['hub', 'spoke'], true) && ! $request->filled('hub_id')) {
                $v->errors()->add('hub_id', 'Hub is required for Hub or Spoke level meetings.');
            }
            if ($level === 'spoke' && ! $request->filled('district_id')) {
                $v->errors()->add('district_id', 'District is required for Spoke level meetings.');
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveLocation(array $validated, User $user): array
    {
        $level = (string) ($validated['meeting_level'] ?? '');
        $hubId = null;
        $hubName = null;
        $districtId = null;
        $districtName = null;

        if (in_array($level, ['hub', 'spoke'], true)) {
            $hub = Hub::query()->findOrFail((int) $validated['hub_id']);
            if ($user->role === 'hub_admin') {
                abort_unless((int) $hub->id === (int) $user->hub_id, 422);
            }
            $hubId = (int) $hub->id;
            $hubName = (string) $hub->name;
        }

        if ($level === 'spoke') {
            $district = District::query()->findOrFail((int) $validated['district_id']);
            abort_unless((int) $district->hub_id === (int) $hubId, 422);
            if (LineDepartmentMeetingAccess::isIncubationManager($user)) {
                abort_unless((int) $district->id === (int) $user->district_id, 422);
            }
            $districtId = (int) $district->id;
            $districtName = (string) $district->name;
        }

        return [
            'hub_id' => $hubId,
            'hub_name' => $hubName,
            'district_id' => $districtId,
            'district_name' => $districtName,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function normalizeIncubateesDiscussed(Request $request): ?array
    {
        $raw = trim((string) $request->input('incubatees_discussed', ''));
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/[\n,;]+/', $raw) ?: [];
        $names = array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            $parts,
        )));

        return $names === [] ? null : $names;
    }

    /**
     * @return array<string, string>
     */
    private function proofMediaUploadErrors(Request $request): array
    {
        $errors = [];
        foreach ((array) $request->file('proof_media', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $errors['proof_media.'.$index] = $this->describeFailedUpload($file);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function photoUploadErrors(Request $request): array
    {
        $errors = [];
        foreach ((array) $request->file('photos', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $errors['photos.'.$index] = $this->describeFailedUpload($file);
            }
        }

        return $errors;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedMedia(array $files, string $directory): array
    {
        $items = [];
        foreach ($files as $media) {
            if (! $media) {
                continue;
            }
            $path = $media->store($directory);
            $mime = (string) ($media->getClientMimeType() ?? '');
            $items[] = [
                'path' => $path,
                'original_name' => (string) $media->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($media->getSize() ?? 0),
                'type' => Str::startsWith($mime, 'image/') ? 'image' : 'document',
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function storeOptionalPhotos(Request $request): ?array
    {
        $files = array_values(array_filter((array) $request->file('photos', [])));

        return $files === [] ? null : $this->storeUploadedMedia($files, 'line-department-meeting-photos');
    }

    private function exportRouteForUser(User $user): string
    {
        if ($user->role === 'state_admin') {
            return 'admin.line-department-meetings.export';
        }

        return LineDepartmentMeetingAccess::routePrefixForUser($user).'line-department-meetings.export';
    }

    /**
     * @return \Illuminate\Support\Collection<int, District>
     */
    private function districtsForUser(?User $user)
    {
        if ($user?->role === 'hub_admin' && (int) ($user->hub_id ?? 0) > 0) {
            return District::query()->where('hub_id', (int) $user->hub_id)->orderBy('name')->get(['id', 'name', 'hub_id']);
        }

        if (LineDepartmentMeetingAccess::isIncubationManager($user) && (int) ($user->district_id ?? 0) > 0) {
            return District::query()->where('id', (int) $user->district_id)->get(['id', 'name', 'hub_id']);
        }

        return District::query()->with('hub:id,name')->orderBy('name')->get(['id', 'name', 'hub_id']);
    }

    /**
     * @param  list<array<string, mixed>|mixed>  $items
     */
    private function deleteStoredMediaFiles(array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }
}
