<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\District;
use App\Models\Hub;
use App\Models\LineDepartmentMeeting;
use App\Models\User;
use App\Support\TodayOnlyDate;
use App\Support\LineDepartmentMeetingAccess;
use App\Support\LineDepartmentMeetingOptions;
use App\Support\MisFieldActivityApproval;
use App\Services\MisFieldActivityWorkflowService;
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

    public function __construct(
        private MisFieldActivityWorkflowService $misFieldWorkflow,
    ) {}

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

        if ($uploadErrors = $this->meetingMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request);
        $location = $this->resolveLocation($validated, $user);

        $mediaFiles = $this->meetingMediaFiles($request);

        $meeting = LineDepartmentMeeting::query()->create(array_merge([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'meeting_date' => $validated['meeting_date'],
            'meeting_level' => (string) $validated['meeting_level'],
            'meeting_mode' => (string) $validated['meeting_mode'],
            'venue' => trim((string) ($validated['venue'] ?? '')) ?: null,
            'department_name' => $this->resolvedDepartmentName($validated),
            'department_unit' => trim((string) ($validated['department_unit'] ?? '')) ?: null,
            'official_name' => trim((string) $validated['official_name']),
            'official_designation' => trim((string) $validated['official_designation']),
            'official_phone' => trim((string) ($validated['official_phone'] ?? '')) ?: null,
            'muy_staff_present' => null,
            'meeting_purpose' => (string) $validated['meeting_purpose'],
            'meeting_purpose_other' => (string) $validated['meeting_purpose'] === 'other'
                ? trim((string) ($validated['meeting_purpose_other'] ?? ''))
                : null,
            'agenda_remark_outcome' => trim((string) $validated['agenda_remark_outcome']),
            'agenda_summary' => '',
            'outcome_decision' => '',
            'incubatees_discussed_json' => null,
            'proof_media_json' => $this->storeUploadedMedia($mediaFiles, 'line-department-meeting-media'),
            'photos_json' => null,
        ], $location));

        $this->misFieldWorkflow->submitForApproval($meeting, (int) $user->id);

        return redirect()
            ->route($prefix.'line-department-meetings.dashboard')
            ->with('status', 'Line department meeting (MIS 12.2) submitted for approval.');
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

        $query = LineDepartmentMeeting::query()->with(['submitter:id,name', 'misFieldSpoc:id,name']);
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
            'canWithdraw' => MisFieldActivityApproval::submitterCanWithdraw($user, $ldmMeeting),
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

        if ($uploadErrors = $this->meetingMediaUploadErrors($request)) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request, $ldmMeeting->meeting_date?->toDateString());
        $location = $this->resolveLocation($validated, $user);

        $newMedia = $this->meetingMediaFiles($request);
        if ($newMedia !== []) {
            $existing = collect($ldmMeeting->meetingMediaItems());
            if ($existing->count() + count($newMedia) > 5) {
                return back()->withInput()->withErrors(['meeting_media' => 'You can upload up to 5 files in total.']);
            }
            $ldmMeeting->proof_media_json = $existing
                ->merge($this->storeUploadedMedia($newMedia, 'line-department-meeting-media'))
                ->values()
                ->all();
            $ldmMeeting->photos_json = null;
        }

        $ldmMeeting->fill(array_merge([
            'meeting_date' => $validated['meeting_date'],
            'meeting_level' => (string) $validated['meeting_level'],
            'meeting_mode' => (string) $validated['meeting_mode'],
            'venue' => trim((string) ($validated['venue'] ?? '')) ?: null,
            'department_name' => $this->resolvedDepartmentName($validated),
            'department_unit' => trim((string) ($validated['department_unit'] ?? '')) ?: null,
            'official_name' => trim((string) $validated['official_name']),
            'official_designation' => trim((string) $validated['official_designation']),
            'official_phone' => trim((string) ($validated['official_phone'] ?? '')) ?: null,
            'muy_staff_present' => null,
            'meeting_purpose' => (string) $validated['meeting_purpose'],
            'meeting_purpose_other' => (string) $validated['meeting_purpose'] === 'other'
                ? trim((string) ($validated['meeting_purpose_other'] ?? ''))
                : null,
            'agenda_remark_outcome' => trim((string) $validated['agenda_remark_outcome']),
            'agenda_summary' => '',
            'outcome_decision' => '',
            'incubatees_discussed_json' => null,
        ], $location));
        $wasResubmit = $ldmMeeting->canBeEditedByMisFieldSubmitter();
        $ldmMeeting->save();

        if ($wasResubmit) {
            $this->misFieldWorkflow->resubmitForApproval($ldmMeeting, (int) $user->id);
        }

        return redirect()->route($prefix.'line-department-meetings.dashboard')->with('status', $wasResubmit
            ? 'Meeting resubmitted for approval.'
            : 'Meeting updated.');
    }

    public function destroy(Request $request, LineDepartmentMeeting $ldmMeeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless(LineDepartmentMeetingAccess::canDelete($user, $ldmMeeting), 403);
        $prefix = LineDepartmentMeetingAccess::routePrefixForUser($user);

        $this->deleteStoredMediaFiles($ldmMeeting->meetingMediaItems());
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
        $items = $ldmMeeting->meetingMediaItems();
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
            'departmentNames' => LineDepartmentMeetingOptions::departmentNames(),
            'selectedDepartment' => $this->selectedDepartmentForForm($row),
            'departmentNameOther' => $this->departmentNameOtherForForm($row),
            'agendaRemarkOutcome' => $this->agendaRemarkOutcomeForForm($row),
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

        LineDepartmentMeetingAccess::applyDistrictStaffVisibilityScope($query, $user);
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

        if (LineDepartmentMeetingAccess::districtStaffCanViewRecord($user, $row)) {
            return true;
        }

        return false;
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
                    ->orWhere('agenda_remark_outcome', 'like', $like)
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
    private function validateSubmission(Request $request, ?string $existingMeetingDate = null): array
    {
        $levelKeys = array_keys(LineDepartmentMeeting::MEETING_LEVELS);
        $modeKeys = array_keys(LineDepartmentMeetingOptions::meetingModes());
        $purposeKeys = array_keys(LineDepartmentMeetingOptions::meetingPurposes());
        $departmentKeys = array_keys(LineDepartmentMeetingOptions::departmentNames());

        $rules = [
            'meeting_date' => $existingMeetingDate !== null
                ? TodayOnlyDate::rulesAllowingExisting($existingMeetingDate)
                : TodayOnlyDate::rules(),
            'meeting_level' => ['required', 'string', Rule::in($levelKeys)],
            'hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'meeting_mode' => ['required', 'string', Rule::in($modeKeys)],
            'venue' => ['nullable', 'string', 'max:191'],
            'department_name' => ['required', 'string', Rule::in($departmentKeys)],
            'department_name_other' => ['nullable', 'string', 'max:191', 'required_if:department_name,Other'],
            'department_unit' => ['nullable', 'string', 'max:191'],
            'official_name' => ['required', 'string', 'max:191'],
            'official_designation' => ['required', 'string', 'max:191'],
            'official_phone' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
            'meeting_purpose' => ['required', 'string', Rule::in($purposeKeys)],
            'meeting_purpose_other' => ['nullable', 'string', 'max:191', 'required_if:meeting_purpose,other'],
            'agenda_remark_outcome' => ['required', 'string', 'max:5000'],
            'meeting_media' => ['nullable', 'array', 'max:5'],
            'meeting_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:51200'],
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
            if ((string) $request->input('department_name') === 'Other'
                && trim((string) $request->input('department_name_other', '')) === '') {
                $v->errors()->add('department_name_other', 'Please specify the department name.');
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvedDepartmentName(array $validated): string
    {
        $name = trim((string) ($validated['department_name'] ?? ''));
        if ($name === 'Other') {
            return trim((string) ($validated['department_name_other'] ?? ''));
        }

        return $name;
    }

    private function selectedDepartmentForForm(?LineDepartmentMeeting $row): string
    {
        $selected = trim((string) old('department_name', ''));
        if ($selected !== '') {
            return $selected;
        }

        if (! $row) {
            return '';
        }

        $existing = trim((string) $row->department_name);
        if ($existing === '') {
            return '';
        }

        if (array_key_exists($existing, LineDepartmentMeetingOptions::departmentNames())) {
            return $existing;
        }

        return 'Other';
    }

    private function departmentNameOtherForForm(?LineDepartmentMeeting $row): string
    {
        $other = trim((string) old('department_name_other', ''));
        if ($other !== '') {
            return $other;
        }

        if (! $row) {
            return '';
        }

        $existing = trim((string) $row->department_name);

        return array_key_exists($existing, LineDepartmentMeetingOptions::departmentNames()) ? '' : $existing;
    }

    private function agendaRemarkOutcomeForForm(?LineDepartmentMeeting $row): string
    {
        $value = trim((string) old('agenda_remark_outcome', ''));
        if ($value !== '') {
            return $value;
        }

        return $row ? $row->agendaRemarkOutcomeDisplay() : '';
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
            if (LineDepartmentMeetingAccess::isDistrictStaffSubmitter($user)) {
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
     * @return list<UploadedFile>
     */
    private function meetingMediaFiles(Request $request): array
    {
        return array_values(array_filter((array) $request->file('meeting_media', [])));
    }

    /**
     * @return array<string, string>
     */
    private function meetingMediaUploadErrors(Request $request): array
    {
        $errors = [];
        foreach ((array) $request->file('meeting_media', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $errors['meeting_media.'.$index] = $this->describeFailedUpload($file);
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

        if (LineDepartmentMeetingAccess::isDistrictStaffSubmitter($user)) {
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
