<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\District;
use App\Models\Hub;
use App\Models\StakeholderConsultationWorkshop;
use App\Support\StakeholderConsultationWorkshopAccess;
use App\Support\StakeholderConsultationWorkshopOptions;
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

class StakeholderConsultationWorkshopController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function create(Request $request): View
    {
        abort_unless(StakeholderConsultationWorkshopAccess::canSubmit($request->user()), 403);

        return view('stakeholder-consultation-workshops.form', $this->formViewData($request, null));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(StakeholderConsultationWorkshopAccess::canSubmit($user), 403);

        if (! Schema::hasTable('stakeholder_consultation_workshops')) {
            return redirect()
                ->route('spoc.stakeholder-consultation-workshops.create')
                ->withErrors(['workshop_title' => 'Table not found. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
            $this->minutesMediaUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request, requireAttendance: true);
        $location = $this->resolveLocation($validated);

        $attendanceFiles = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($attendanceFiles === []) {
            return back()->withInput()->withErrors([
                'attendance_media' => 'Upload at least one attendance sheet.',
            ]);
        }

        StakeholderConsultationWorkshop::query()->create(array_merge([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'workshop_date' => $validated['workshop_date'],
            'workshop_title' => trim((string) $validated['workshop_title']),
            'workshop_mode' => (string) $validated['workshop_mode'],
            'venue' => trim((string) $validated['venue']),
            'organizing_level' => (string) $validated['organizing_level'],
            'primary_departments_json' => array_values((array) $validated['primary_departments']),
            'other_departments' => trim((string) ($validated['other_departments'] ?? '')) ?: null,
            'stakeholder_types_json' => array_values((array) $validated['stakeholder_types']),
            'total_participants' => (int) $validated['total_participants'],
            'officials_count' => isset($validated['officials_count']) ? (int) $validated['officials_count'] : null,
            'consultation_theme' => trim((string) $validated['consultation_theme']),
            'key_outcomes' => trim((string) $validated['key_outcomes']),
            'mou_convergence_planned' => ($validated['mou_convergence_planned'] ?? null) ?: null,
            'attendance_media_json' => $this->storeUploadedMedia($attendanceFiles, 'stakeholder-consultation-workshop-media'),
            'workshop_photos_json' => $this->storeOptionalPhotos($request),
            'minutes_media_json' => $this->storeOptionalMinutes($request),
        ], $location));

        return redirect()
            ->route('spoc.stakeholder-consultation-workshops.dashboard')
            ->with('status', 'Stakeholder consultation workshop (MIS 12.1) submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(StakeholderConsultationWorkshopAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('stakeholder_consultation_workshops')) {
            return view('stakeholder-consultation-workshops.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'totals' => ['workshops' => 0, 'participants' => 0],
                'canSubmit' => StakeholderConsultationWorkshopAccess::canSubmit($user),
            ]);
        }

        $query = StakeholderConsultationWorkshop::query()->with('submitter:id,name');
        $this->applyListFilters($query, $request);

        $totals = [
            'workshops' => (int) (clone $query)->count(),
            'participants' => (int) (clone $query)->sum('total_participants'),
        ];

        $rows = $query->orderByDesc('workshop_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('stakeholder-consultation-workshops.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'currentRole' => (string) $user->role,
            'canSubmit' => StakeholderConsultationWorkshopAccess::canSubmit($user),
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
            'totals' => $totals,
            'exportRoute' => $user->role === 'state_admin'
                ? 'admin.stakeholder-consultation-workshops.export'
                : 'spoc.stakeholder-consultation-workshops.export',
        ]);
    }

    public function show(Request $request, StakeholderConsultationWorkshop $scwWorkshop): View
    {
        $user = $request->user();
        abort_unless(StakeholderConsultationWorkshopAccess::canViewDashboard($user), 403);
        $scwWorkshop->loadMissing('submitter:id,name');

        return view('stakeholder-consultation-workshops.show', [
            'row' => $scwWorkshop,
            'currentRole' => (string) $user->role,
            'canEdit' => StakeholderConsultationWorkshopAccess::canEdit($user, $scwWorkshop),
            'canDelete' => StakeholderConsultationWorkshopAccess::canDelete($user, $scwWorkshop),
        ]);
    }

    public function edit(Request $request, StakeholderConsultationWorkshop $scwWorkshop): View
    {
        abort_unless(StakeholderConsultationWorkshopAccess::canEdit($request->user(), $scwWorkshop), 403);

        return view('stakeholder-consultation-workshops.form', $this->formViewData($request, $scwWorkshop));
    }

    public function update(Request $request, StakeholderConsultationWorkshop $scwWorkshop): RedirectResponse
    {
        $user = $request->user();
        abort_unless(StakeholderConsultationWorkshopAccess::canEdit($user, $scwWorkshop), 403);

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
            $this->minutesMediaUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $hasExistingAttendance = $scwWorkshop->hasAttendanceSheet();
        $validated = $this->validateSubmission($request, requireAttendance: ! $hasExistingAttendance);
        $location = $this->resolveLocation($validated);

        $newAttendance = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($newAttendance === [] && ! $hasExistingAttendance) {
            return back()->withInput()->withErrors(['attendance_media' => 'Upload at least one attendance sheet.']);
        }

        if ($newAttendance !== []) {
            $existing = collect((array) $scwWorkshop->attendance_media_json)->filter(fn ($item): bool => is_array($item))->values();
            if ($existing->count() + count($newAttendance) > 5) {
                return back()->withInput()->withErrors(['attendance_media' => 'You can upload up to 5 attendance files per workshop.']);
            }
            $scwWorkshop->attendance_media_json = $existing->merge($this->storeUploadedMedia($newAttendance, 'stakeholder-consultation-workshop-media'))->values()->all();
        }

        $this->mergeOptionalUploads($request, $scwWorkshop);

        $scwWorkshop->fill(array_merge([
            'workshop_date' => $validated['workshop_date'],
            'workshop_title' => trim((string) $validated['workshop_title']),
            'workshop_mode' => (string) $validated['workshop_mode'],
            'venue' => trim((string) $validated['venue']),
            'organizing_level' => (string) $validated['organizing_level'],
            'primary_departments_json' => array_values((array) $validated['primary_departments']),
            'other_departments' => trim((string) ($validated['other_departments'] ?? '')) ?: null,
            'stakeholder_types_json' => array_values((array) $validated['stakeholder_types']),
            'total_participants' => (int) $validated['total_participants'],
            'officials_count' => isset($validated['officials_count']) ? (int) $validated['officials_count'] : null,
            'consultation_theme' => trim((string) $validated['consultation_theme']),
            'key_outcomes' => trim((string) $validated['key_outcomes']),
            'mou_convergence_planned' => ($validated['mou_convergence_planned'] ?? null) ?: null,
        ], $location));
        $scwWorkshop->save();

        return redirect()->route('spoc.stakeholder-consultation-workshops.dashboard')
            ->with('status', 'Workshop updated.');
    }

    public function destroy(Request $request, StakeholderConsultationWorkshop $scwWorkshop): RedirectResponse
    {
        abort_unless(StakeholderConsultationWorkshopAccess::canDelete($request->user(), $scwWorkshop), 403);
        $this->deleteStoredMediaFiles((array) $scwWorkshop->attendance_media_json);
        $this->deleteStoredMediaFiles((array) $scwWorkshop->workshop_photos_json);
        $this->deleteStoredMediaFiles((array) $scwWorkshop->minutes_media_json);
        $scwWorkshop->delete();

        return redirect()->route('spoc.stakeholder-consultation-workshops.dashboard')
            ->with('status', 'Workshop deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(StakeholderConsultationWorkshopAccess::canViewDashboard($request->user()), 403);
        $query = StakeholderConsultationWorkshop::query();
        $this->applyListFilters($query, $request);
        $rows = $query->orderByDesc('workshop_date')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'Title', 'Mode', 'Level', 'Venue', 'Departments', 'Participants', 'Entered by']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->workshop_date?->format('Y-m-d') ?? '',
                    $row->workshop_title,
                    $row->formattedWorkshopMode(),
                    $row->organizingLevelLabel(),
                    $row->venue,
                    $row->primaryDepartmentsLabel(),
                    (int) $row->total_participants,
                    $row->submitted_by_name,
                ]);
            }
            fclose($out);
        }, 'stakeholder-consultation-workshops-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadAttachment(
        Request $request,
        StakeholderConsultationWorkshop $scwWorkshop,
    ): StreamedResponse|BinaryFileResponse {
        abort_unless(StakeholderConsultationWorkshopAccess::canViewDashboard($request->user()), 403);

        $index = max(0, (int) $request->query('index', 0));
        $collection = (string) $request->query('collection', 'attendance');
        $items = match ($collection) {
            'photos' => (array) $scwWorkshop->workshop_photos_json,
            'minutes' => (array) $scwWorkshop->minutes_media_json,
            default => (array) $scwWorkshop->attendance_media_json,
        };
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
    private function formViewData(Request $request, ?StakeholderConsultationWorkshop $row): array
    {
        return [
            'user' => $request->user(),
            'row' => $row,
            'migrationMissing' => ! Schema::hasTable('stakeholder_consultation_workshops'),
            'storeRoute' => $row ? 'spoc.stakeholder-consultation-workshops.update' : 'spoc.stakeholder-consultation-workshops.store',
            'dashboardRoute' => $request->user()?->role === 'state_admin'
                ? 'admin.stakeholder-consultation-workshops.dashboard'
                : 'spoc.stakeholder-consultation-workshops.dashboard',
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->with('hub:id,name')->orderBy('name')->get(['id', 'name', 'hub_id']),
            'lineDepartments' => StakeholderConsultationWorkshopOptions::lineDepartments(),
            'stakeholderTypes' => StakeholderConsultationWorkshopOptions::stakeholderTypes(),
            'organizingLevels' => StakeholderConsultationWorkshop::ORGANIZING_LEVELS,
            'mouOptions' => StakeholderConsultationWorkshop::MOU_CONVERGENCE_OPTIONS,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<StakeholderConsultationWorkshop>  $query
     */
    private function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('workshop_title', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhere('consultation_theme', 'like', $like);
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('workshop_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('workshop_date', '<=', (string) $request->query('to'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, bool $requireAttendance = false): array
    {
        $deptKeys = array_keys(StakeholderConsultationWorkshopOptions::lineDepartments());
        $stakeholderKeys = array_keys(StakeholderConsultationWorkshopOptions::stakeholderTypes());
        $levelKeys = array_keys(StakeholderConsultationWorkshop::ORGANIZING_LEVELS);
        $mouKeys = array_keys(StakeholderConsultationWorkshop::MOU_CONVERGENCE_OPTIONS);

        $rules = [
            'workshop_date' => ['required', 'date'],
            'workshop_title' => ['required', 'string', 'max:191'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'venue' => ['required', 'string', 'max:191'],
            'organizing_level' => ['required', 'string', Rule::in($levelKeys)],
            'hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'primary_departments' => ['required', 'array', 'min:1'],
            'primary_departments.*' => ['string', Rule::in($deptKeys)],
            'other_departments' => ['nullable', 'string', 'max:5000'],
            'stakeholder_types' => ['required', 'array', 'min:1'],
            'stakeholder_types.*' => ['string', Rule::in($stakeholderKeys)],
            'total_participants' => ['required', 'integer', 'min:1'],
            'officials_count' => ['nullable', 'integer', 'min:0'],
            'consultation_theme' => ['required', 'string', 'max:5000'],
            'key_outcomes' => ['required', 'string', 'max:5000'],
            'mou_convergence_planned' => ['nullable', 'string', Rule::in($mouKeys)],
            'attendance_media' => [$requireAttendance ? 'required' : 'nullable', 'array', 'max:5'],
            'attendance_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:51200'],
            'workshop_photos' => ['nullable', 'array', 'max:3'],
            'workshop_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'minutes_media' => ['nullable', 'array', 'max:3'],
            'minutes_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:51200'],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request): void {
            $level = (string) $request->input('organizing_level', '');
            if (in_array($level, ['hub', 'spoke'], true) && ! $request->filled('hub_id')) {
                $v->errors()->add('hub_id', 'Hub is required for Hub or Spoke level workshops.');
            }
            if ($level === 'spoke' && ! $request->filled('district_id')) {
                $v->errors()->add('district_id', 'District is required for Spoke level workshops.');
            }
            if ($level === 'spoke' && $request->filled('hub_id') && $request->filled('district_id')) {
                $district = District::query()->find((int) $request->input('district_id'));
                if ($district && (int) $district->hub_id !== (int) $request->input('hub_id')) {
                    $v->errors()->add('district_id', 'District must belong to the selected hub.');
                }
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveLocation(array $validated): array
    {
        $level = (string) ($validated['organizing_level'] ?? '');
        $hubId = null;
        $hubName = null;
        $districtId = null;
        $districtName = null;

        if (in_array($level, ['hub', 'spoke'], true)) {
            $hub = Hub::query()->findOrFail((int) $validated['hub_id']);
            $hubId = (int) $hub->id;
            $hubName = (string) $hub->name;
        }

        if ($level === 'spoke') {
            $district = District::query()->findOrFail((int) $validated['district_id']);
            abort_unless((int) $district->hub_id === (int) $hubId, 422);
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
     * @return array<string, string>
     */
    private function workshopPhotosUploadErrors(Request $request): array
    {
        $errors = [];
        foreach ((array) $request->file('workshop_photos', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $errors['workshop_photos.'.$index] = $this->describeFailedUpload($file);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function minutesMediaUploadErrors(Request $request): array
    {
        $errors = [];
        foreach ((array) $request->file('minutes_media', []) as $index => $file) {
            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $errors['minutes_media.'.$index] = $this->describeFailedUpload($file);
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
        $files = array_values(array_filter((array) $request->file('workshop_photos', [])));

        return $files === [] ? null : $this->storeUploadedMedia($files, 'stakeholder-consultation-workshop-photos');
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function storeOptionalMinutes(Request $request): ?array
    {
        $files = array_values(array_filter((array) $request->file('minutes_media', [])));

        return $files === [] ? null : $this->storeUploadedMedia($files, 'stakeholder-consultation-workshop-minutes');
    }

    private function mergeOptionalUploads(Request $request, StakeholderConsultationWorkshop $row): void
    {
        $newPhotos = array_values(array_filter((array) $request->file('workshop_photos', [])));
        if ($newPhotos !== []) {
            $existing = collect((array) $row->workshop_photos_json)->filter(fn ($item): bool => is_array($item))->values();
            if ($existing->count() + count($newPhotos) > 3) {
                abort(422, 'You can upload up to 3 workshop photos.');
            }
            $row->workshop_photos_json = $existing->merge($this->storeUploadedMedia($newPhotos, 'stakeholder-consultation-workshop-photos'))->values()->all();
        }

        $newMinutes = array_values(array_filter((array) $request->file('minutes_media', [])));
        if ($newMinutes !== []) {
            $existing = collect((array) $row->minutes_media_json)->filter(fn ($item): bool => is_array($item))->values();
            if ($existing->count() + count($newMinutes) > 3) {
                abort(422, 'You can upload up to 3 minutes files.');
            }
            $row->minutes_media_json = $existing->merge($this->storeUploadedMedia($newMinutes, 'stakeholder-consultation-workshop-minutes'))->values()->all();
        }
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
