<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\DistrictWorkshopSession;
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

class DistrictWorkshopSessionAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        return view('staff.district-workshop-sessions.form', [
            'user' => $user,
            'migrationMissing' => ! Schema::hasTable('district_workshop_sessions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        if (! Schema::hasTable('district_workshop_sessions')) {
            return redirect()
                ->route('staff.district-workshop-sessions.create')
                ->withErrors(['session_date' => 'District workshop sessions table is missing. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $this->validateDistrictWorkshopSubmission($request, requiresPhotosUpload: true);

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required to submit attendance.');

        $mediaItems = $this->storeUploadedMedia((array) $request->file('attendance_media', []));
        $photoItems = $this->storeUploadedPhotos((array) $request->file('workshop_photos', []));

        DistrictWorkshopSession::query()->create([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'event_date' => $validated['session_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'workshop_mode' => (string) $validated['workshop_mode'],
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'male_participants' => (int) $validated['male_participants'],
            'female_participants' => (int) $validated['female_participants'],
            'attendance_media_json' => $mediaItems,
            'workshop_photos_json' => $photoItems,
            'selected_incubatee_ids' => [],
            'selected_incubatees_snapshot' => [],
        ]);

        return redirect()
            ->route('staff.district-workshop-sessions.dashboard')
            ->with('status', 'District level workshop attendance submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard($user->role), 403);

        if (! Schema::hasTable('district_workshop_sessions')) {
            return view('staff.district-workshop-sessions.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'totals' => ['male' => 0, 'female' => 0, 'participants' => 0],
            ]);
        }

        $query = DistrictWorkshopSession::query()->with(['district:id,name', 'submitter:id,name']);

        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('workshop_mode', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $eventPeriod = $this->applyEventPeriodFilter($query, $request);

        $totals = [
            'male' => (int) (clone $query)->sum('male_participants'),
            'female' => (int) (clone $query)->sum('female_participants'),
        ];
        $totals['participants'] = $totals['male'] + $totals['female'];

        $rows = $query
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $filterYear = (int) ($eventPeriod['event_year'] !== '' ? $eventPeriod['event_year'] : now()->year);

        return view('staff.district-workshop-sessions.dashboard', [
            'rows' => $rows,
            'migrationMissing' => false,
            'isPaginated' => true,
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
            'totals' => $totals,
        ]);
    }

    public function destroy(Request $request, DistrictWorkshopSession $districtWorkshopSession): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($districtWorkshopSession, (int) $user->id);

        $this->deleteMediaFiles((array) $districtWorkshopSession->attendance_media_json);
        $this->deleteMediaFiles((array) $districtWorkshopSession->workshop_photos_json);
        $districtWorkshopSession->delete();

        return redirect()
            ->route('staff.district-workshop-sessions.dashboard')
            ->with('status', 'District level workshop entry deleted.');
    }

    public function show(Request $request, DistrictWorkshopSession $districtWorkshopSession): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $districtWorkshopSession);

        $districtWorkshopSession->loadMissing(['district:id,name', 'submitter:id,name']);

        return view('staff.district-workshop-sessions.show', [
            'row' => $districtWorkshopSession,
            'currentRole' => (string) $user->role,
            'canEdit' => (string) $user->role === 'district_staff'
                && (int) $districtWorkshopSession->submitted_by_user_id === (int) $user->id,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);

        $query = DistrictWorkshopSession::query()->with(['district:id,name', 'submitter:id,name']);
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('workshop_mode', 'like', $like);
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

        return $this->streamExportCsv(
            $rows,
            'district-workshop-sessions-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportSingle(Request $request, DistrictWorkshopSession $districtWorkshopSession): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $districtWorkshopSession);

        return $this->streamExportCsv(
            collect([$districtWorkshopSession]),
            'district-workshop-session-'.$districtWorkshopSession->id.'-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function edit(Request $request, DistrictWorkshopSession $districtWorkshopSession): View
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($districtWorkshopSession, (int) $user->id);

        return view('staff.district-workshop-sessions.edit', [
            'user' => $user,
            'row' => $districtWorkshopSession,
        ]);
    }

    public function update(Request $request, DistrictWorkshopSession $districtWorkshopSession): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($districtWorkshopSession, (int) $user->id);

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->workshopPhotosUploadErrors($request),
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $existingPhotosCount = count((array) $districtWorkshopSession->workshop_photos_json);
        $validated = $this->validateDistrictWorkshopSubmission(
            $request,
            requiresPhotosUpload: $existingPhotosCount === 0,
        );

        $newUploads = array_values(array_filter((array) $request->file('attendance_media', [])));
        if ($newUploads !== []) {
            $existingMedia = collect((array) $districtWorkshopSession->attendance_media_json)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
            $combinedCount = $existingMedia->count() + count($newUploads);
            abort_if($combinedCount > 25, 422, 'You can upload up to 25 attendance files per session.');

            $districtWorkshopSession->attendance_media_json = $existingMedia
                ->merge($this->storeUploadedMedia($newUploads))
                ->values()
                ->all();
        }

        $newPhotos = array_values(array_filter((array) $request->file('workshop_photos', [])));
        if ($newPhotos !== []) {
            $existingPhotos = collect((array) $districtWorkshopSession->workshop_photos_json)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
            $combinedPhotoCount = $existingPhotos->count() + count($newPhotos);
            if ($combinedPhotoCount > 5) {
                return back()
                    ->withInput()
                    ->withErrors(['workshop_photos' => 'You can upload up to 5 workshop photos per session.']);
            }

            $districtWorkshopSession->workshop_photos_json = $existingPhotos
                ->merge($this->storeUploadedPhotos($newPhotos))
                ->values()
                ->all();
        } elseif ($existingPhotosCount === 0) {
            return back()
                ->withInput()
                ->withErrors(['workshop_photos' => 'Please upload at least one workshop photo (minimum 1, maximum 5).']);
        }

        $districtWorkshopSession->event_date = $validated['session_date'];
        $districtWorkshopSession->workshop_mode = (string) $validated['workshop_mode'];
        $districtWorkshopSession->notes = trim((string) ($validated['notes'] ?? '')) ?: null;
        $districtWorkshopSession->male_participants = (int) $validated['male_participants'];
        $districtWorkshopSession->female_participants = (int) $validated['female_participants'];
        $districtWorkshopSession->save();

        return redirect()
            ->route('staff.district-workshop-sessions.dashboard')
            ->with('status', 'District level workshop attendance updated.');
    }

    public function downloadAttachment(Request $request, DistrictWorkshopSession $districtWorkshopSession): StreamedResponse|BinaryFileResponse
    {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $districtWorkshopSession);

        $index = max(0, (int) $request->query('index', 0));
        $collection = (string) $request->query('collection', 'attendance');
        $items = match ($collection) {
            'photos' => (array) $districtWorkshopSession->workshop_photos_json,
            default => (array) $districtWorkshopSession->attendance_media_json,
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
     * @return array<string, mixed>
     */
    private function validateDistrictWorkshopSubmission(
        Request $request,
        bool $requiresPhotosUpload,
    ): array {
        $validator = Validator::make($request->all(), [
            'session_date' => ['required', 'date'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'male_participants' => ['required', 'integer', 'min:0'],
            'female_participants' => ['required', 'integer', 'min:0'],
            'attendance_media' => ['nullable', 'array', 'max:25'],
            'attendance_media.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:51200'],
            'workshop_photos' => $requiresPhotosUpload
                ? ['required', 'array', 'min:1', 'max:5']
                : ['nullable', 'array', 'max:5'],
            'workshop_photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $male = (int) $request->input('male_participants', 0);
            $female = (int) $request->input('female_participants', 0);
            if ($male + $female < 1) {
                $validator->errors()->add('male_participants', 'Enter at least one male or female participant.');
            }
        });

        return $validator->validate();
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

            $mediaPath = $media->store('district-workshop-session-media');
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

            $photoPath = $photo->store('district-workshop-session-photos');
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
     * @param  list<array<string, mixed>|mixed>  $mediaItems
     */
    private function deleteMediaFiles(array $mediaItems): void
    {
        foreach ($mediaItems as $media) {
            if (! is_array($media)) {
                continue;
            }

            $path = (string) ($media['path'] ?? '');
            if ($path !== '' && Storage::exists($path)) {
                Storage::delete($path);
            }
        }
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

    private function streamExportCsv(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Entry ID',
            'Date of Session',
            'Session Taken By',
            'District',
            'Session type',
            'Workshop mode',
            'Notes',
            'Male Participants',
            'Female Participants',
            'Total Participants',
            'Attendance Files Count',
            'Workshop Photos Count',
            'Attendance Status',
            'Created At',
            'Updated At',
        ];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                $entry = $row instanceof DistrictWorkshopSession ? $row : null;
                if (! $entry) {
                    continue;
                }

                fputcsv($out, [
                    (string) $entry->id,
                    (string) ($entry->event_date?->format('Y-m-d') ?? ''),
                    (string) $entry->submitted_by_name,
                    (string) ($entry->district_name ?: ($entry->district?->name ?? '')),
                    (string) $entry->formatted_program_type,
                    (string) $entry->formatted_workshop_mode,
                    (string) ($entry->notes ?? ''),
                    (string) (int) ($entry->male_participants ?? 0),
                    (string) (int) ($entry->female_participants ?? 0),
                    (string) $entry->totalParticipantCount(),
                    (string) count((array) $entry->attendance_media_json),
                    (string) count((array) $entry->workshop_photos_json),
                    $entry->hasAttendanceSheet() ? 'Uploaded' : 'Pending',
                    (string) ($entry->created_at?->format('Y-m-d H:i:s') ?? ''),
                    (string) ($entry->updated_at?->format('Y-m-d H:i:s') ?? ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function canSubmit(string $role): bool
    {
        return $role === 'district_staff';
    }

    private function canViewDashboard(string $role): bool
    {
        return in_array($role, ['district_staff', 'state_staff', 'state_admin'], true);
    }

    private function assertCanEdit(DistrictWorkshopSession $row, int $userId): void
    {
        abort_unless((int) $row->submitted_by_user_id === $userId, 403);
    }

    private function assertCanAccessRecord(string $role, int $userDistrictId, DistrictWorkshopSession $row): void
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
