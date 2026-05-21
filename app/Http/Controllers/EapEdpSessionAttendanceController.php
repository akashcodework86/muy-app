<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAttendanceMediaUploads;
use App\Models\EapEdpSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EapEdpSessionAttendanceController extends Controller
{
    use ValidatesAttendanceMediaUploads;

    private const PROGRAM_TYPE = 'eap_edp';

    public function create(Request $request): View
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        return view('staff.eap-edp-sessions.form', [
            'user' => $user,
            'migrationMissing' => ! Schema::hasTable('eap_edp_sessions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->load('district');
        abort_unless($this->canSubmit($user->role), 403);

        if (! Schema::hasTable('eap_edp_sessions')) {
            return redirect()
                ->route('staff.eap-edp-sessions.create')
                ->withErrors(['venue_name_address' => 'EAP/EDP sessions table is missing. Please run migrations first.']);
        }

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->sessionPhotosUploadErrors($request)
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate(array_merge([
            'session_date' => ['required', 'date'],
            'venue_name_address' => ['required', 'string', 'max:5000'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attendance_male_count' => ['required', 'integer', 'min:0'],
            'attendance_female_count' => ['required', 'integer', 'min:0'],
        ], $this->attendanceMediaValidationRules(), $this->sessionPhotosValidationRules(requirePhotos: true), [
            'attendance_media' => ['nullable', 'array', 'max:25'],
        ]));

        $districtId = (int) ($user->district_id ?: 0);
        abort_unless($districtId > 0, 422, 'District assignment is required to submit attendance.');

        $male = (int) $validated['attendance_male_count'];
        $female = (int) $validated['attendance_female_count'];
        $total = $male + $female;

        $mediaItems = $this->storeUploadedMedia((array) $request->file('attendance_media', []));
        $photoItems = $this->storeUploadedPhotos($this->sessionPhotoUploads($request));
        if ($photoItems === []) {
            return back()
                ->withInput()
                ->withErrors(['session_photos' => 'Upload at least one session photo.']);
        }

        $sessionPayload = [
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'event_date' => $validated['session_date'],
            'district_id' => $districtId,
            'district_name' => (string) ($user->district?->name ?? ''),
            'program_type' => self::PROGRAM_TYPE,
            'workshop_mode' => (string) $validated['workshop_mode'],
            'attendance_male_count' => $male,
            'attendance_female_count' => $female,
            'attendance_total_count' => $total,
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'attendance_media_json' => $mediaItems,
            'selected_incubatee_ids' => [],
            'selected_incubatees_snapshot' => [],
        ];

        $this->applyVenueToPayload($sessionPayload, trim((string) $validated['venue_name_address']));

        if (Schema::hasColumn('eap_edp_sessions', 'session_photos_json')) {
            $sessionPayload['session_photos_json'] = $photoItems;
        }

        EapEdpSession::query()->create($sessionPayload);

        return redirect()
            ->route('staff.eap-edp-sessions.dashboard')
            ->with('status', 'EAP/EDP session attendance submitted.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard($user->role), 403);

        if (! Schema::hasTable('eap_edp_sessions')) {
            return view('staff.eap-edp-sessions.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
            ]);
        }

        $query = EapEdpSession::query()->with(['district:id,name', 'submitter:id,name']);

        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like, $search): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('topic', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('workshop_mode', 'like', $like);
                if (Schema::hasColumn('eap_edp_sessions', 'venue_name_address')) {
                    $q->orWhere('venue_name_address', 'like', $like);
                }
                if (ctype_digit($search)) {
                    $n = (int) $search;
                    $q->orWhere('attendance_male_count', $n)
                        ->orWhere('attendance_female_count', $n)
                        ->orWhere('attendance_total_count', $n);
                }
            });
        }

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

        return view('staff.eap-edp-sessions.dashboard', [
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
        ]);
    }

    public function show(Request $request, EapEdpSession $eapEdpSession): View
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $eapEdpSession);

        $eapEdpSession->loadMissing(['district:id,name', 'submitter:id,name']);

        return view('staff.eap-edp-sessions.show', [
            'row' => $eapEdpSession,
            'currentRole' => (string) $user->role,
            'canEdit' => (string) $user->role === 'district_staff'
                && (int) $eapEdpSession->submitted_by_user_id === (int) $user->id,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);

        $query = EapEdpSession::query()->with(['district:id,name', 'submitter:id,name']);
        if ($user->role === 'district_staff') {
            $query->where('district_id', (int) ($user->district_id ?: 0));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like, $search): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('district_name', 'like', $like)
                    ->orWhere('topic', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('workshop_mode', 'like', $like);
                if (Schema::hasColumn('eap_edp_sessions', 'venue_name_address')) {
                    $q->orWhere('venue_name_address', 'like', $like);
                }
                if (ctype_digit($search)) {
                    $n = (int) $search;
                    $q->orWhere('attendance_male_count', $n)
                        ->orWhere('attendance_female_count', $n)
                        ->orWhere('attendance_total_count', $n);
                }
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
            'eap-edp-sessions-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportSingle(Request $request, EapEdpSession $eapEdpSession): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canViewDashboard((string) $user->role), 403);
        $this->assertCanAccessRecord((string) $user->role, (int) ($user->district_id ?: 0), $eapEdpSession);

        return $this->streamExportCsv(
            collect([$eapEdpSession]),
            'eap-edp-session-'.$eapEdpSession->id.'-'.now()->format('Ymd_His').'.csv'
        );
    }

    public function edit(Request $request, EapEdpSession $eapEdpSession): View
    {
        $user = $request->user()->load('district');
        $this->assertCanEdit($eapEdpSession, (int) $user->id);

        return view('staff.eap-edp-sessions.edit', [
            'user' => $user,
            'row' => $eapEdpSession,
        ]);
    }

    public function update(Request $request, EapEdpSession $eapEdpSession): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($eapEdpSession, (int) $user->id);

        if ($uploadErrors = array_merge(
            $this->attendanceMediaUploadErrors($request),
            $this->sessionPhotosUploadErrors($request)
        )) {
            return back()->withInput()->withErrors($uploadErrors);
        }

        $validated = $request->validate(array_merge([
            'session_date' => ['required', 'date'],
            'venue_name_address' => ['required', 'string', 'max:5000'],
            'workshop_mode' => ['required', 'string', 'in:virtual,physical'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attendance_male_count' => ['required', 'integer', 'min:0'],
            'attendance_female_count' => ['required', 'integer', 'min:0'],
            'remove_media_indices' => ['nullable', 'array'],
            'remove_media_indices.*' => ['integer', 'min:0'],
            'remove_photo_indices' => ['nullable', 'array'],
            'remove_photo_indices.*' => ['integer', 'min:0'],
        ], $this->attendanceMediaValidationRules(), $this->sessionPhotosValidationRules(requirePhotos: false), [
            'attendance_media' => ['nullable', 'array', 'max:25'],
            'session_photos' => ['nullable', 'array', 'max:25'],
        ]));

        $male = (int) $validated['attendance_male_count'];
        $female = (int) $validated['attendance_female_count'];
        $total = $male + $female;

        $existingMedia = collect((array) $eapEdpSession->attendance_media_json)
            ->filter(fn ($item): bool => is_array($item))
            ->values();
        $removeIndices = collect($request->input('remove_media_indices', []))
            ->map(fn ($index): int => (int) $index)
            ->unique()
            ->values();

        $keptMedia = $existingMedia
            ->filter(fn ($item, int $index): bool => ! $removeIndices->contains($index))
            ->values();

        $removedMedia = $existingMedia
            ->filter(fn ($item, int $index): bool => $removeIndices->contains($index))
            ->values();

        $newUploads = array_values(array_filter((array) $request->file('attendance_media', [])));

        $combinedCount = $keptMedia->count() + count($newUploads);
        abort_if($combinedCount > 25, 422, 'You can upload up to 25 files per session.');

        $this->deleteMediaFiles($removedMedia->all());

        $eapEdpSession->attendance_media_json = $keptMedia
            ->merge($this->storeUploadedMedia($newUploads))
            ->values()
            ->all();

        $existingPhotos = collect((array) $eapEdpSession->session_photos_json)
            ->filter(fn ($item): bool => is_array($item))
            ->values();
        $removePhotoIndices = collect($request->input('remove_photo_indices', []))
            ->map(fn ($index): int => (int) $index)
            ->unique()
            ->values();
        $keptPhotos = $existingPhotos
            ->filter(fn ($item, int $index): bool => ! $removePhotoIndices->contains($index))
            ->values();
        $removedPhotos = $existingPhotos
            ->filter(fn ($item, int $index): bool => $removePhotoIndices->contains($index))
            ->values();
        $newPhotos = $this->sessionPhotoUploads($request);
        $photoCount = $keptPhotos->count() + count($newPhotos);
        if ($photoCount < 1) {
            return back()
                ->withInput()
                ->withErrors(['session_photos' => 'At least one session photo is required. Keep an existing photo or upload a new one.']);
        }
        abort_if($photoCount > 25, 422, 'You can upload up to 25 session photos per entry.');
        $this->deleteMediaFiles($removedPhotos->all());
        if (Schema::hasColumn('eap_edp_sessions', 'session_photos_json')) {
            $eapEdpSession->session_photos_json = $keptPhotos
                ->merge($this->storeUploadedPhotos($newPhotos))
                ->values()
                ->all();
        }

        $eapEdpSession->event_date = $validated['session_date'];
        $eapEdpSession->program_type = self::PROGRAM_TYPE;
        $this->applyVenueToModel($eapEdpSession, trim((string) $validated['venue_name_address']));
        $eapEdpSession->workshop_mode = (string) $validated['workshop_mode'];
        $eapEdpSession->attendance_male_count = $male;
        $eapEdpSession->attendance_female_count = $female;
        $eapEdpSession->attendance_total_count = $total;
        $eapEdpSession->notes = trim((string) ($validated['notes'] ?? '')) ?: null;
        $eapEdpSession->save();

        return redirect()
            ->route('staff.eap-edp-sessions.dashboard')
            ->with('status', 'EAP/EDP session attendance updated.');
    }

    public function destroy(Request $request, EapEdpSession $eapEdpSession): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanEdit($eapEdpSession, (int) $user->id);

        $this->deleteMediaFiles((array) $eapEdpSession->attendance_media_json);
        $this->deleteMediaFiles((array) $eapEdpSession->session_photos_json);
        $eapEdpSession->delete();

        return redirect()
            ->route('staff.eap-edp-sessions.dashboard')
            ->with('status', 'EAP/EDP session entry deleted.');
    }

    public function downloadAttachment(Request $request, EapEdpSession $eapEdpSession): StreamedResponse|BinaryFileResponse
    {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $eapEdpSession);

        $index = max(0, (int) $request->query('index', 0));
        $media = collect((array) $eapEdpSession->attendance_media_json)->get($index);
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

    public function downloadPhoto(Request $request, EapEdpSession $eapEdpSession): StreamedResponse|BinaryFileResponse
    {
        $this->assertCanAccessRecord($request->user()->role, (int) ($request->user()->district_id ?: 0), $eapEdpSession);

        $index = max(0, (int) $request->query('index', 0));
        $photo = collect((array) $eapEdpSession->session_photos_json)->get($index);
        abort_if(! is_array($photo), 404);

        $path = (string) ($photo['path'] ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::exists($path), 404);

        $filename = (string) ($photo['original_name'] ?? basename($path));
        $mime = (string) ($photo['mime'] ?? 'image/jpeg');

        if ($request->boolean('inline')) {
            return response()->file(Storage::path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'image/jpeg',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::download($path, $filename);
    }

    /**
     * @return array<string, list<string>>
     */
    private function sessionPhotosValidationRules(bool $requirePhotos): array
    {
        return [
            'session_photos' => $requirePhotos
                ? ['required', 'array', 'min:1', 'max:25']
                : ['nullable', 'array', 'max:25'],
            'session_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,heic,heif', 'max:10240'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyVenueToPayload(array &$payload, string $venue): void
    {
        if (Schema::hasColumn('eap_edp_sessions', 'venue_name_address')) {
            $payload['venue_name_address'] = $venue;
            $payload['topic'] = null;
        } else {
            $payload['topic'] = Str::limit($venue, 191, '');
        }
    }

    private function applyVenueToModel(EapEdpSession $session, string $venue): void
    {
        if (Schema::hasColumn('eap_edp_sessions', 'venue_name_address')) {
            $session->venue_name_address = $venue;
            $session->topic = null;
        } else {
            $session->topic = Str::limit($venue, 191, '');
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function sessionPhotoUploads(Request $request): array
    {
        $files = $request->file('session_photos');
        if ($files === null) {
            return [];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [$files],
            fn ($file): bool => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    /**
     * @return array<string, string>
     */
    private function sessionPhotosUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ($this->sessionPhotoUploads($request) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['session_photos.'.$index] = $this->describeFailedUpload($file);
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
            if (! $photo instanceof UploadedFile || ! $photo->isValid()) {
                continue;
            }

            if (! $this->isAllowedSessionPhoto($photo)) {
                continue;
            }

            $photoPath = $photo->store('eap-edp-session-photos');
            $mime = (string) ($photo->getClientMimeType() ?? 'image/jpeg');
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

    private function isAllowedSessionPhoto(UploadedFile $photo): bool
    {
        $mime = strtolower((string) $photo->getMimeType());
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower((string) $photo->getClientOriginalExtension());

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'], true);
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

            $mediaPath = $media->store('eap-edp-session-media');
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
            'Venue name and address',
            'Workshop mode',
            'Notes',
            'Attendance sheet files',
            'Session photos',
            'Male count',
            'Female count',
            'Total attendance',
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
                $entry = $row instanceof EapEdpSession ? $row : null;
                if (! $entry) {
                    continue;
                }

                fputcsv($out, [
                    (string) $entry->id,
                    (string) ($entry->event_date?->format('Y-m-d') ?? ''),
                    (string) $entry->submitted_by_name,
                    (string) ($entry->district_name ?: ($entry->district?->name ?? '')),
                    (string) $entry->formatted_program_type,
                    (string) $entry->display_venue,
                    (string) $entry->formatted_workshop_mode,
                    (string) ($entry->notes ?? ''),
                    (string) count((array) $entry->attendance_media_json),
                    (string) count((array) $entry->session_photos_json),
                    (string) (int) ($entry->attendance_male_count ?? 0),
                    (string) (int) ($entry->attendance_female_count ?? 0),
                    (string) (int) ($entry->attendance_total_count ?? 0),
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

    private function assertCanEdit(EapEdpSession $row, int $userId): void
    {
        abort_unless((int) $row->submitted_by_user_id === $userId, 403);
    }

    private function assertCanAccessRecord(string $role, int $userDistrictId, EapEdpSession $row): void
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
