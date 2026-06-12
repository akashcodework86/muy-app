<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\StakeholderCapacityBuildingSession;
use App\Support\CapacityBuildingStakeholdersAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StakeholderCapacityBuildingSessionController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function create(Request $request): View
    {
        abort_unless(CapacityBuildingStakeholdersAccess::canSubmit($request->user()), 403);

        return view('capacity-building-stakeholders.form', [
            'user' => $request->user(),
            'migrationMissing' => ! Schema::hasTable('stakeholder_capacity_building_sessions'),
            'storeRoute' => 'spoc.capacity-building-stakeholders.store',
            'dashboardRoute' => 'spoc.capacity-building-stakeholders.dashboard',
            'stakeholderTypes' => StakeholderCapacityBuildingSession::STAKEHOLDER_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(CapacityBuildingStakeholdersAccess::canSubmit($user), 403);

        if (! Schema::hasTable('stakeholder_capacity_building_sessions')) {
            return redirect()
                ->route('spoc.capacity-building-stakeholders.create')
                ->withErrors(['session_title' => 'Table not found. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateSubmission($request, requireAttendance: true);

        $attendanceFiles = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($attendanceFiles === []) {
            return back()->withInput()->withErrors([
                'attendance_media' => 'Upload at least one attendance sheet.',
            ]);
        }

        $photoFiles = array_values(array_filter((array) $request->file('workshop_photos', [])));

        StakeholderCapacityBuildingSession::query()->create([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'session_date' => $validated['session_date'],
            'workshop_mode' => (string) $validated['workshop_mode'],
            'venue' => trim((string) $validated['venue']),
            'stakeholder_type' => (string) $validated['stakeholder_type'],
            'stakeholder_type_other' => $this->resolvedStakeholderTypeOther($validated),
            'department_name' => $this->resolvedDepartmentName($validated),
            'session_title' => trim((string) $validated['session_title']),
            'topics_covered' => trim((string) ($validated['topics_covered'] ?? '')) ?: null,
            'staff_trained_total' => (int) $validated['staff_trained_total'],
            'attendance_media_json' => $this->storeUploadedMedia($attendanceFiles),
            'workshop_photos_json' => $photoFiles !== [] ? $this->storeUploadedPhotos($photoFiles) : null,
        ]);

        return redirect()
            ->route('spoc.capacity-building-stakeholders.dashboard')
            ->with('status', 'Capacity building session (MIS 3.4) submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(CapacityBuildingStakeholdersAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('stakeholder_capacity_building_sessions')) {
            return view('capacity-building-stakeholders.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'totals' => ['sessions' => 0, 'staff' => 0, 'with_photos' => 0],
                'canSubmit' => CapacityBuildingStakeholdersAccess::canSubmit($user),
            ]);
        }

        $query = StakeholderCapacityBuildingSession::query()->with('submitter:id,name');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('session_title', 'like', $like)
                    ->orWhere('topics_covered', 'like', $like)
                    ->orWhere('department_name', 'like', $like)
                    ->orWhere('stakeholder_type_other', 'like', $like);
            });
        }

        if ($request->filled('stakeholder')) {
            $query->where('stakeholder_type', (string) $request->query('stakeholder'));
        }

        if ($request->filled('from')) {
            $query->whereDate('session_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('session_date', '<=', (string) $request->query('to'));
        }

        $totals = [
            'sessions' => (int) (clone $query)->count(),
            'staff' => (int) (clone $query)->sum('staff_trained_total'),
            'with_photos' => (int) (clone $query)
                ->whereNotNull('workshop_photos_json')
                ->where('workshop_photos_json', '!=', '[]')
                ->where('workshop_photos_json', '!=', 'null')
                ->count(),
        ];

        $rows = $query
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('capacity-building-stakeholders.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
            'currentRole' => (string) $user->role,
            'canSubmit' => CapacityBuildingStakeholdersAccess::canSubmit($user),
            'stakeholderTypes' => StakeholderCapacityBuildingSession::STAKEHOLDER_TYPES,
            'filters' => [
                'q' => $search,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'stakeholder' => (string) $request->query('stakeholder', ''),
            ],
            'totals' => $totals,
            'exportRoute' => $this->exportRouteForRole((string) $user->role),
        ]);
    }

    public function show(Request $request, StakeholderCapacityBuildingSession $cbsSession): View
    {
        $user = $request->user();
        abort_unless(CapacityBuildingStakeholdersAccess::canViewDashboard($user), 403);

        $cbsSession->loadMissing('submitter:id,name');

        return view('capacity-building-stakeholders.show', [
            'row' => $cbsSession,
            'currentRole' => (string) $user->role,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(CapacityBuildingStakeholdersAccess::canViewDashboard($user), 403);

        $query = StakeholderCapacityBuildingSession::query();
        $this->applyListFilters($query, $request);

        $rows = $query->orderByDesc('session_date')->orderByDesc('id')->get();

        $filename = 'capacity-building-stakeholders-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Date', 'Mode', 'Venue', 'Stakeholder type', 'Department',
                'Title', 'Topics', 'Staff trained', 'Entered by',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->session_date?->format('Y-m-d') ?? '',
                    $row->formattedWorkshopMode(),
                    $row->venue,
                    $row->stakeholderTypeLabel(),
                    $row->department_name ?? '',
                    $row->session_title,
                    $row->topics_covered ?? '',
                    (int) $row->staff_trained_total,
                    $row->submitted_by_name,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadAttachment(
        Request $request,
        StakeholderCapacityBuildingSession $cbsSession,
    ): StreamedResponse|BinaryFileResponse {
        abort_unless(CapacityBuildingStakeholdersAccess::canViewDashboard($request->user()), 403);

        $index = max(0, (int) $request->query('index', 0));
        $collection = (string) $request->query('collection', 'attendance');
        $items = match ($collection) {
            'photos' => (array) $cbsSession->workshop_photos_json,
            default => (array) $cbsSession->attendance_media_json,
        };
        $media = collect($items)->get($index);
        abort_if(! is_array($media), 404);

        $path = (string) ($media['path'] ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::exists($path), 404);

        $filename = (string) ($media['original_name'] ?? basename($path));
        $mime = (string) ($media['mime'] ?? '');
        $inline = $request->boolean('inline') && Str::startsWith($mime, 'image/');

        if ($inline) {
            return response()->file(Storage::path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::download($path, $filename);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<StakeholderCapacityBuildingSession>  $query
     */
    private function applyListFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('session_title', 'like', $like);
            });
        }

        if ($request->filled('stakeholder')) {
            $query->where('stakeholder_type', (string) $request->query('stakeholder'));
        }

        if ($request->filled('from')) {
            $query->whereDate('session_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('session_date', '<=', (string) $request->query('to'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, bool $requireAttendance = false): array
    {
        $typeKeys = array_keys(StakeholderCapacityBuildingSession::STAKEHOLDER_TYPES);

        $rules = array_merge([
            'session_date' => ['required', 'date'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'venue' => ['required', 'string', 'max:191'],
            'stakeholder_type' => ['required', 'string', 'in:'.implode(',', $typeKeys)],
            'stakeholder_type_other' => ['nullable', 'string', 'max:191'],
            'department_name' => ['nullable', 'string', 'max:191'],
            'session_title' => ['required', 'string', 'max:191'],
            'topics_covered' => ['nullable', 'string', 'max:5000'],
            'staff_trained_total' => ['required', 'integer', 'min:1'],
            'attendance_media' => [$requireAttendance ? 'required' : 'nullable', 'array', 'max:5'],
            'attendance_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:51200'],
            'workshop_photos' => ['nullable', 'array', 'max:3'],
            'workshop_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ]);

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($v) use ($request): void {
            $type = (string) $request->input('stakeholder_type', '');
            if ($type === 'other' && trim((string) $request->input('stakeholder_type_other', '')) === '') {
                $v->errors()->add('stakeholder_type_other', 'Specify the stakeholder type when Other is selected.');
            }
            if (in_array($type, ['line_department', 'other'], true) && trim((string) $request->input('department_name', '')) === '') {
                $v->errors()->add('department_name', 'Department name is required for Line department or Other.');
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvedStakeholderTypeOther(array $validated): ?string
    {
        if (($validated['stakeholder_type'] ?? '') !== 'other') {
            return null;
        }

        $value = trim((string) ($validated['stakeholder_type_other'] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvedDepartmentName(array $validated): ?string
    {
        $type = (string) ($validated['stakeholder_type'] ?? '');
        if (! in_array($type, ['line_department', 'other'], true)) {
            return null;
        }

        $value = trim((string) ($validated['department_name'] ?? ''));

        return $value !== '' ? $value : null;
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

            $photoPath = $photo->store('capacity-building-stakeholder-photos');
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

            $mediaPath = $media->store('capacity-building-stakeholder-media');
            $mime = (string) ($media->getClientMimeType() ?? '');
            $type = Str::startsWith($mime, 'image/') ? 'image' : 'document';

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

    private function exportRouteForRole(string $role): string
    {
        return match ($role) {
            'state_admin' => 'admin.capacity-building-stakeholders.export',
            default => 'spoc.capacity-building-stakeholders.export',
        };
    }
}
