<?php

namespace App\Http\Controllers;

use App\Models\BuyerSellerMeetAttendanceFile;
use App\Models\BuyerSellerMeetEntry;
use App\Models\BuyerSellerMeetParticipant;
use App\Models\BuyerSellerMeetPhoto;
use App\Models\District;
use App\Models\User;
use App\Services\PitchDeckIncubateeCatalogService;
use App\Support\ActiveFiscalYearDate;
use App\Support\BrandingCommunicationAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuyerSellerMeetController extends Controller
{
    public function __construct(
        private readonly PitchDeckIncubateeCatalogService $incubateeCatalog,
    ) {}

    public function create(Request $request): View
    {
        $this->submitterOrAbort($request);

        return $this->formView($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('buyer_seller_meet_entries')) {
            return redirect()
                ->route('spoc.buyer-seller-meets.create')
                ->withErrors(['title' => 'Database table is missing. Please run migrations first.']);
        }

        $validated = $this->validateEntry($request, null);
        $participants = $this->normalizeParticipants($request);
        $this->assertParticipantRules($participants);

        $duplicateWarning = $this->duplicateWarning(
            (int) $validated['district_id'],
            (string) $validated['meet_date'],
        );

        $fy = ActiveFiscalYearDate::activeFiscalYear();

        DB::transaction(function () use ($user, $validated, $participants, $request, $fy): void {
            $entry = BuyerSellerMeetEntry::query()->create([
                'meet_date' => $validated['meet_date'],
                'district_id' => (int) $validated['district_id'],
                'fiscal_year_id' => $fy?->id,
                'title' => trim((string) $validated['title']),
                'venue' => trim((string) $validated['venue']),
                'total_participants' => (int) $validated['total_participants'],
                'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
                'submitted_by_user_id' => (int) $user->id,
                'submitted_by_name' => (string) $user->name,
            ]);

            $this->storePhotos($entry, array_values(array_filter((array) $request->file('photos', []))));
            $this->storeAttendanceFiles($entry, array_values(array_filter((array) $request->file('attendance_files', []))));
            $this->persistParticipants($entry, $participants);
        });

        $redirect = redirect()
            ->route('spoc.buyer-seller-meets.dashboard')
            ->with('status', 'Buyer-Seller Meet logged successfully.');

        if ($duplicateWarning !== null) {
            $redirect->with('warning', $duplicateWarning);
        }

        return $redirect;
    }

    public function edit(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): View
    {
        abort_unless($request->user()?->role === 'state_admin', 403);
        $buyerSellerMeet->loadMissing(['district', 'photos', 'attendanceFiles', 'participants']);

        return $this->formView($request, $buyerSellerMeet, isAdminEdit: true);
    }

    public function update(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): RedirectResponse
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        $validated = $this->validateEntry($request, $buyerSellerMeet);
        $participants = $this->normalizeParticipants($request);
        $this->assertParticipantRules($participants);

        $existingDate = $buyerSellerMeet->meet_date?->format('Y-m-d');
        $fy = ActiveFiscalYearDate::activeFiscalYear();

        DB::transaction(function () use ($buyerSellerMeet, $validated, $participants, $request, $fy, $existingDate): void {
            $buyerSellerMeet->update([
                'meet_date' => $validated['meet_date'],
                'district_id' => (int) $validated['district_id'],
                'fiscal_year_id' => $fy?->id,
                'title' => trim((string) $validated['title']),
                'venue' => trim((string) $validated['venue']),
                'total_participants' => (int) $validated['total_participants'],
                'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
            ]);

            if ($request->hasFile('photos')) {
                foreach ($buyerSellerMeet->photos as $photo) {
                    Storage::disk((string) $photo->disk)->delete((string) $photo->path);
                }
                $buyerSellerMeet->photos()->delete();
                $this->storePhotos($buyerSellerMeet, array_values(array_filter((array) $request->file('photos', []))));
            }

            if ($request->hasFile('attendance_files')) {
                foreach ($buyerSellerMeet->attendanceFiles as $file) {
                    Storage::disk((string) $file->disk)->delete((string) $file->path);
                }
                $buyerSellerMeet->attendanceFiles()->delete();
                $this->storeAttendanceFiles($buyerSellerMeet, array_values(array_filter((array) $request->file('attendance_files', []))));
            }

            $buyerSellerMeet->participants()->delete();
            $this->persistParticipants($buyerSellerMeet, $participants);
        });

        return redirect()
            ->route('admin.buyer-seller-meets.show', $buyerSellerMeet)
            ->with('status', 'Buyer-Seller Meet updated.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('buyer_seller_meet_entries')) {
            return $this->dashboardView($request, collect(), true, ['q' => '', 'from' => '', 'to' => '', 'district_id' => '']);
        }

        $query = BuyerSellerMeetEntry::query()->with(['district.hub', 'submitter:id,name']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('venue', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like)
                    ->orWhereHas('district', fn ($d) => $d->where('name', 'like', $like));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('meet_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('meet_date', '<=', (string) $request->query('to'));
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', (int) $request->query('district_id'));
        }

        $rows = $query
            ->orderByDesc('meet_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'district_id' => (string) $request->query('district_id', ''),
        ]);
    }

    public function show(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $buyerSellerMeet);

        $buyerSellerMeet->loadMissing(['district.hub', 'photos', 'attendanceFiles', 'participants']);

        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'spoc';

        return view('buyer-seller-meets.show', [
            'row' => $buyerSellerMeet,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $routePrefix.'.buyer-seller-meets.dashboard',
            'photoRoute' => $routePrefix.'.buyer-seller-meets.photo',
            'attendanceRoute' => $routePrefix.'.buyer-seller-meets.attendance',
            'editRoute' => $isAdmin ? 'admin.buyer-seller-meets.edit' : null,
            'destroyRoute' => $isAdmin ? 'admin.buyer-seller-meets.destroy' : null,
        ]);
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            BrandingCommunicationAccess::canSubmit($user) || $user?->role === 'state_admin',
            403
        );

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $results = collect($this->incubateeCatalog->search((string) $validated['q']))
            ->filter(fn (array $row): bool => (bool) ($row['is_onboarded'] ?? false))
            ->values()
            ->take(15)
            ->all();

        return response()->json(['results' => $results]);
    }

    public function downloadPhoto(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $buyerSellerMeet);

        $photoId = (int) $request->query('photo', 0);
        $photo = $buyerSellerMeet->photos()->where('id', $photoId)->first();
        if ($photo === null) {
            abort(404);
        }

        $inline = (bool) $request->query('inline', false);
        $disk = Storage::disk((string) $photo->disk);
        $filename = (string) ($photo->original_name ?: 'photo');

        if ($inline && str_starts_with((string) $photo->mime, 'image/')) {
            return $disk->response((string) $photo->path, $filename);
        }

        return $disk->download((string) $photo->path, $filename);
    }

    public function downloadAttendance(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $buyerSellerMeet);

        $fileId = (int) $request->query('file', 0);
        $file = $buyerSellerMeet->attendanceFiles()->where('id', $fileId)->first();
        if ($file === null) {
            abort(404);
        }

        return Storage::disk((string) $file->disk)
            ->download((string) $file->path, (string) ($file->original_name ?: 'attendance'));
    }

    public function destroy(Request $request, BuyerSellerMeetEntry $buyerSellerMeet): RedirectResponse
    {
        abort_unless($request->user()?->role === 'state_admin', 403);

        $this->deleteEntryFiles($buyerSellerMeet);
        $buyerSellerMeet->delete();

        return redirect()
            ->route('admin.buyer-seller-meets.dashboard')
            ->with('status', 'Buyer-Seller Meet deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        $query = BuyerSellerMeetEntry::query()->with(['district']);
        $this->scopeDashboardQuery($query, $user);

        $rows = $query->orderByDesc('meet_date')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['#', 'Date', 'District', 'Title', 'Venue', 'Total participants', 'Buyers', 'Sellers', 'Submitted by']);

            $serial = 1;
            foreach ($rows as $row) {
                if (! $row instanceof BuyerSellerMeetEntry) {
                    continue;
                }

                $row->load('participants');
                $buyers = $row->participants->where('role', BuyerSellerMeetParticipant::ROLE_BUYER)->count();
                $sellers = $row->participants->where('role', BuyerSellerMeetParticipant::ROLE_SELLER)->count();

                fputcsv($out, [
                    $serial++,
                    $row->meet_date?->format('Y-m-d') ?? '',
                    $row->district?->name ?? '',
                    $row->title,
                    $row->venue,
                    $row->total_participants,
                    $buyers,
                    $sellers,
                    $row->submitted_by_name,
                ]);
            }

            fclose($out);
        }, 'buyer-seller-meets-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formView(Request $request, ?BuyerSellerMeetEntry $entry, bool $isAdminEdit = false): View
    {
        $user = $request->user();
        if ($isAdminEdit) {
            abort_unless($user?->role === 'state_admin', 403);
        } else {
            $this->submitterOrAbort($request);
        }

        $districts = District::query()->orderBy('name')->get(['id', 'name']);
        $fy = ActiveFiscalYearDate::activeFiscalYear();

        return view('buyer-seller-meets.form', [
            'user' => $user,
            'entry' => $entry,
            'isAdminEdit' => $isAdminEdit,
            'migrationMissing' => ! Schema::hasTable('buyer_seller_meet_entries'),
            'storeRoute' => $isAdminEdit ? 'admin.buyer-seller-meets.update' : 'spoc.buyer-seller-meets.store',
            'formAction' => $isAdminEdit
                ? route('admin.buyer-seller-meets.update', $entry)
                : route('spoc.buyer-seller-meets.store'),
            'storeMethod' => $isAdminEdit ? 'put' : 'post',
            'dashboardRoute' => $isAdminEdit ? 'admin.buyer-seller-meets.dashboard' : 'spoc.buyer-seller-meets.dashboard',
            'searchRoute' => 'spoc.buyer-seller-meets.incubatees.search',
            'districts' => $districts,
            'activeFyLabel' => $fy?->code ?? 'active FY',
            'fyMinDate' => $fy?->starts_on?->format('Y-m-d'),
            'fyMaxDate' => $fy?->ends_on?->format('Y-m-d'),
            'maxPhotos' => (int) config('branding_communication.buyer_seller_meet_max_photos', 10),
            'maxAttendanceFiles' => (int) config('branding_communication.buyer_seller_meet_max_attendance_files', 5),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request, ?BuyerSellerMeetEntry $existing): array
    {
        $maxPhotos = (int) config('branding_communication.buyer_seller_meet_max_photos', 10);
        $maxAttendance = (int) config('branding_communication.buyer_seller_meet_max_attendance_files', 5);
        $existingDate = $existing?->meet_date?->format('Y-m-d');

        $dateRules = $existing === null
            ? ActiveFiscalYearDate::rules()
            : ActiveFiscalYearDate::rulesAllowingExisting($existingDate);

        $photoRules = $existing === null
            ? ['required', 'array', 'min:1', 'max:'.$maxPhotos]
            : ['nullable', 'array', 'max:'.$maxPhotos];
        $attendanceRules = $existing === null
            ? ['required', 'array', 'min:1', 'max:'.$maxAttendance]
            : ['nullable', 'array', 'max:'.$maxAttendance];

        return $request->validate([
            'meet_date' => $dateRules,
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'venue' => ['required', 'string', 'max:255'],
            'total_participants' => ['required', 'integer', 'min:1', 'max:100000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'photos' => $photoRules,
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'attendance_files' => $attendanceRules,
            'attendance_files.*' => ['file', 'mimes:pdf,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'participants' => ['required', 'array', 'min:2'],
            'participants.*.role' => ['required', Rule::in([BuyerSellerMeetParticipant::ROLE_BUYER, BuyerSellerMeetParticipant::ROLE_SELLER])],
            'participants.*.kind' => ['required', Rule::in([BuyerSellerMeetParticipant::KIND_LINKED, BuyerSellerMeetParticipant::KIND_MANUAL])],
            'participants.*.participant_name' => ['required', 'string', 'max:255'],
            'participants.*.phone' => ['nullable', 'string', 'max:32'],
            'participants.*.application_no' => ['nullable', 'string', 'max:64'],
            'participants.*.incubatee_key' => ['nullable', 'string', 'max:64'],
            'participants.*.cfa_submission_id' => ['nullable', 'integer', 'min:1'],
            'participants.*.legacy_application_id' => ['nullable', 'integer', 'min:1'],
            'participants.*.legacy_phase1_application_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'photos.required' => 'Upload at least one photo.',
            'photos.min' => 'Upload at least one photo.',
            'attendance_files.required' => 'Upload at least one attendance sheet file.',
            'attendance_files.min' => 'Upload at least one attendance sheet file.',
            'participants.min' => 'Add at least one buyer and one seller.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeParticipants(Request $request): array
    {
        $rows = [];
        foreach ((array) $request->input('participants', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['participant_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'kind' => (string) ($row['kind'] ?? BuyerSellerMeetParticipant::KIND_MANUAL),
                'role' => (string) ($row['role'] ?? ''),
                'participant_name' => $name,
                'phone' => $this->nullableTrim($row['phone'] ?? null),
                'application_no' => $this->nullableTrim($row['application_no'] ?? null),
                'incubatee_key' => $this->nullableTrim($row['incubatee_key'] ?? null),
                'cfa_submission_id' => isset($row['cfa_submission_id']) && $row['cfa_submission_id'] !== '' ? (int) $row['cfa_submission_id'] : null,
                'legacy_application_id' => isset($row['legacy_application_id']) && $row['legacy_application_id'] !== '' ? (int) $row['legacy_application_id'] : null,
                'legacy_phase1_application_id' => isset($row['legacy_phase1_application_id']) && $row['legacy_phase1_application_id'] !== '' ? (int) $row['legacy_phase1_application_id'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $participants
     */
    private function assertParticipantRules(array $participants): void
    {
        $buyers = collect($participants)->where('role', BuyerSellerMeetParticipant::ROLE_BUYER)->count();
        $sellers = collect($participants)->where('role', BuyerSellerMeetParticipant::ROLE_SELLER)->count();

        if ($buyers < 1 || $sellers < 1) {
            throw ValidationException::withMessages([
                'participants' => 'Add at least one buyer and one seller participant.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $participants
     */
    private function persistParticipants(BuyerSellerMeetEntry $entry, array $participants): void
    {
        foreach ($participants as $index => $row) {
            BuyerSellerMeetParticipant::query()->create([
                'buyer_seller_meet_entry_id' => (int) $entry->id,
                'participant_kind' => (string) $row['kind'],
                'role' => (string) $row['role'],
                'incubatee_key' => $row['incubatee_key'],
                'cfa_submission_id' => $row['cfa_submission_id'],
                'legacy_application_id' => $row['legacy_application_id'],
                'legacy_phase1_application_id' => $row['legacy_phase1_application_id'],
                'participant_name' => (string) $row['participant_name'],
                'application_no' => $row['application_no'],
                'phone' => $row['phone'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storePhotos(BuyerSellerMeetEntry $entry, array $files): void
    {
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $stored = $this->storeUploadedFile($file, 'buyer-seller-meets/photos');
            BuyerSellerMeetPhoto::query()->create([
                'buyer_seller_meet_entry_id' => (int) $entry->id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['name'],
                'mime' => $stored['mime'],
                'size_bytes' => $stored['size'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storeAttendanceFiles(BuyerSellerMeetEntry $entry, array $files): void
    {
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $stored = $this->storeUploadedFile($file, 'buyer-seller-meets/attendance');
            BuyerSellerMeetAttendanceFile::query()->create([
                'buyer_seller_meet_entry_id' => (int) $entry->id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['name'],
                'mime' => $stored['mime'],
                'size_bytes' => $stored['size'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array{disk: string, path: string, name: string, mime: string, size: int}
     */
    private function storeUploadedFile(UploadedFile $file, string $folder): array
    {
        $original = $file->getClientOriginalName() ?: 'file';
        $disk = (string) config('filesystems.default', 'local');
        $path = $file->storeAs(
            $folder.'/'.now()->format('Y/m'),
            Str::uuid()->toString().'_'.Str::slug(pathinfo($original, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
            $disk,
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $original,
            'mime' => (string) ($file->getClientMimeType() ?? ''),
            'size' => (int) ($file->getSize() ?? 0),
        ];
    }

    private function duplicateWarning(int $districtId, string $meetDate): ?string
    {
        $exists = BuyerSellerMeetEntry::query()
            ->where('district_id', $districtId)
            ->whereDate('meet_date', $meetDate)
            ->exists();

        return $exists
            ? 'Note: another Buyer-Seller Meet is already logged for this district on the same date.'
            : null;
    }

    private function deleteEntryFiles(BuyerSellerMeetEntry $entry): void
    {
        $entry->loadMissing(['photos', 'attendanceFiles']);
        foreach ($entry->photos as $photo) {
            Storage::disk((string) $photo->disk)->delete((string) $photo->path);
        }
        foreach ($entry->attendanceFiles as $file) {
            Storage::disk((string) $file->disk)->delete((string) $file->path);
        }
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canSubmit($user), 403);

        return $user;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, array $filters): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';

        return view('buyer-seller-meets.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
            'dashboardRoute' => $isAdmin ? 'admin.buyer-seller-meets.dashboard' : 'spoc.buyer-seller-meets.dashboard',
            'exportRoute' => $isAdmin ? 'admin.buyer-seller-meets.export' : 'spoc.buyer-seller-meets.export',
            'showRoute' => $isAdmin ? 'admin.buyer-seller-meets.show' : 'spoc.buyer-seller-meets.show',
            'createRoute' => BrandingCommunicationAccess::canSubmit($user) ? 'spoc.buyer-seller-meets.create' : null,
        ]);
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, BuyerSellerMeetEntry $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff'
            && BrandingCommunicationAccess::canSubmit($user)
            && (int) $row->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }
}
