<?php

namespace App\Http\Controllers;

use App\Models\DemoDay;
use App\Models\User;
use App\Services\DemoDayIncubateeCatalogService;
use App\Support\DemoDayOptions;
use App\Support\DemoDaysDeliverablesSupport;
use App\Support\FundingSchematicConvergenceAccess;
use App\Support\IncubateeAttendeeCounts;
use App\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemoDayController extends Controller
{
    public function __construct(
        private readonly DemoDayIncubateeCatalogService $incubateeCatalog,
    ) {}

    public function create(Request $request): View
    {
        $this->submitterOrAbort($request);

        return $this->formView($request->user(), null);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);
        $this->assertTableExists();

        $validated = $this->validateSubmission($request, requirePhotos: true);
        $participating = $this->resolveParticipatingIncubateesOrFail($validated);
        $photoItems = $this->storeUploadedEventPhotos(
            array_values(array_filter((array) $request->file('event_photos', []))),
        );

        DemoDay::query()->create(array_merge(
            $this->payloadFromValidated($validated, $participating, $user),
            [
                'event_photos_json' => $photoItems,
                'proof_file_disk' => '',
                'proof_file_path' => '',
                'proof_file_name' => '',
            ],
        ));

        return redirect()
            ->route('spoc.demo-days.dashboard')
            ->with('status', 'Demo day entry saved (MIS 8.4).');
    }

    public function edit(Request $request, DemoDay $demoDay): View
    {
        $user = $this->submitterOrAbort($request);
        abort_unless(FundingSchematicConvergenceAccess::canEdit($user, (int) $demoDay->entered_by_user_id), 403);

        return $this->formView($user, $demoDay);
    }

    public function update(Request $request, DemoDay $demoDay): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);
        abort_unless(FundingSchematicConvergenceAccess::canEdit($user, (int) $demoDay->entered_by_user_id), 403);
        $this->assertTableExists();

        $validated = $this->validateSubmission($request, requirePhotos: ! $demoDay->hasEventPhotos());
        $participating = $this->resolveParticipatingIncubateesOrFail($validated);

        $payload = $this->payloadFromValidated($validated, $participating, $user);

        $newPhotos = array_values(array_filter((array) $request->file('event_photos', [])));
        if ($newPhotos !== []) {
            $existingPhotos = collect($demoDay->eventPhotoItems())
                ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['path'] ?? '')) !== '')
                ->values()
                ->all();

            if (count($existingPhotos) + count($newPhotos) > 5) {
                throw ValidationException::withMessages([
                    'event_photos' => 'You can upload up to 5 event photos per demo day.',
                ]);
            }

            $payload['event_photos_json'] = array_merge(
                $existingPhotos,
                $this->storeUploadedEventPhotos($newPhotos),
            );
            $payload['proof_file_disk'] = '';
            $payload['proof_file_path'] = '';
            $payload['proof_file_name'] = '';
        }

        $demoDay->update($payload);

        return redirect()
            ->route('spoc.demo-days.dashboard')
            ->with('status', 'Demo day entry updated.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('demo_days')) {
            return $this->dashboardView($request, collect(), true, [
                'q' => '', 'from' => '', 'to' => '', 'event_type' => '',
            ], ['events' => 0, 'total_participants' => 0]);
        }

        $query = DemoDay::query()->with(['district:id,name']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('event_name', 'like', $like)
                    ->orWhere('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('investor_name', 'like', $like)
                    ->orWhere('entered_by_name', 'like', $like)
                    ->orWhere('venue', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', (string) $request->query('event_type'));
        }

        $statsQuery = clone $query;
        $statsRows = (clone $statsQuery)->get();
        $totals = [
            'events' => $statsRows->count(),
            'total_participants' => (int) $statsRows->sum(
                fn (DemoDay $row): int => $row->participantCounts()['total']
            ),
        ];

        $rows = $query->orderByDesc('event_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'event_type' => (string) $request->query('event_type', ''),
        ], $totals);
    }

    public function show(Request $request, DemoDay $demoDay): View
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $demoDay);

        $demoDay->loadMissing('district.hub:id,name');
        $isAdmin = $user->role === 'state_admin';

        return view('demo-days.show', [
            'row' => $demoDay,
            'participatingIncubatees' => $demoDay->participatingIncubatees(),
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $isAdmin ? 'admin.demo-days.dashboard' : 'spoc.demo-days.dashboard',
            'attachmentRoute' => $isAdmin ? 'admin.demo-days.attachment' : 'spoc.demo-days.attachment',
            'editRoute' => FundingSchematicConvergenceAccess::canEdit($user, (int) $demoDay->entered_by_user_id)
                ? 'spoc.demo-days.edit'
                : null,
            'canDelete' => FundingSchematicConvergenceAccess::canDelete($user, (int) $demoDay->entered_by_user_id),
        ]);
    }

    public function destroy(Request $request, DemoDay $demoDay): RedirectResponse
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canDelete($user, (int) $demoDay->entered_by_user_id), 403);

        $demoDay->delete();

        $route = $user->role === 'state_admin'
            ? 'admin.demo-days.dashboard'
            : 'spoc.demo-days.dashboard';

        return redirect()->route($route)->with('status', 'Demo day entry deleted.');
    }

    public function downloadAttachment(Request $request, DemoDay $demoDay): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $demoDay);

        $index = max(0, (int) $request->query('index', 0));
        $items = $demoDay->eventPhotoItems();
        $media = $items[$index] ?? null;
        abort_unless(is_array($media), 404);

        $path = (string) ($media['path'] ?? '');
        abort_if($path === '', 404);

        $disk = (string) ($media['disk'] ?? config('filesystems.default'));
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $filename = (string) ($media['original_name'] ?? basename($path));
        $mime = (string) ($media['mime'] ?? '');
        $inline = $request->boolean('inline') && (
            str_starts_with($mime, 'image/')
            || preg_match('/\.(jpe?g|png|webp|gif)$/i', $filename) === 1
        );

        if ($inline) {
            return response()->file(Storage::disk($disk)->path($path), [
                'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return Storage::disk($disk)->download($path, $filename);
    }

    /** @deprecated Use downloadAttachment */
    public function downloadProof(Request $request, DemoDay $demoDay): StreamedResponse|BinaryFileResponse
    {
        return $this->downloadAttachment($request, $demoDay);
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        abort_unless(FundingSchematicConvergenceAccess::canSubmit($request->user()), 403);

        $term = trim((string) $request->query('q', ''));

        return response()->json([
            'results' => $this->incubateeCatalog->search($term),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canViewDashboard($user), 403);

        $query = DemoDay::query()->with('district:id,name');
        $this->scopeDashboardQuery($query, $user);

        if ($request->filled('from')) {
            $query->whereDate('event_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('event_date', '<=', (string) $request->query('to'));
        }

        $rows = $query->orderByDesc('event_date')->orderByDesc('id')->get();
        $filename = 'demo-days-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID', 'Event date', 'Event name', 'Type', 'Investor', 'Venue', 'Mode',
                'Participants', 'Incubatee', 'Application no', 'District',
                'Entered by', 'Created at',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->event_date?->format('Y-m-d'),
                    $row->event_name,
                    DemoDayOptions::eventTypeLabel((string) $row->event_type, $row->event_type_other),
                    $row->investor_name,
                    $row->venue,
                    DemoDayOptions::modeLabel($row->mode),
                    $row->participantCounts()['total'],
                    $row->incubateeNamesSummary(),
                    collect($row->participatingIncubatees())->pluck('application_no')->filter()->implode('; '),
                    $row->district?->name,
                    $row->entered_by_name,
                    $row->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    private function storeUploadedEventPhotos(array $files): array
    {
        $disk = (string) config('filesystems.default', 'local');
        $photoItems = [];

        foreach ($files as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            $name = (string) $photo->getClientOriginalName();
            $path = $photo->storeAs(
                'demo-days/'.now()->format('Y/m'),
                Str::uuid()->toString().'_'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.$photo->getClientOriginalExtension(),
                $disk,
            );
            $mime = (string) ($photo->getClientMimeType() ?? '');

            $photoItems[] = [
                'path' => $path,
                'disk' => $disk,
                'original_name' => $name,
                'mime' => $mime,
                'size_bytes' => (int) ($photo->getSize() ?? 0),
                'type' => 'image',
            ];
        }

        return $photoItems;
    }

    /**
     * @return array<string, string>
     */
    private function eventPhotosUploadErrors(Request $request): array
    {
        $errors = [];

        foreach ((array) $request->file('event_photos', []) as $index => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            $errors['event_photos.'.$index] = 'Photo upload failed. Try a smaller JPG or PNG file.';
        }

        return $errors;
    }

    private function formView(User $user, ?DemoDay $row): View
    {
        return view('demo-days.form', [
            'user' => $user,
            'row' => $row,
            'migrationMissing' => ! Schema::hasTable('demo_days'),
            'storeRoute' => $row ? 'spoc.demo-days.update' : 'spoc.demo-days.store',
            'dashboardRoute' => 'spoc.demo-days.dashboard',
            'searchRoute' => 'spoc.demo-days.incubatees.search',
            'attachmentRoute' => 'spoc.demo-days.attachment',
            'eventTypes' => DemoDayOptions::eventTypes(),
            'modes' => DemoDayOptions::modes(),
        ]);
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(FundingSchematicConvergenceAccess::canSubmit($user), 403);

        return $user;
    }

    private function assertTableExists(): void
    {
        if (! Schema::hasTable('demo_days')) {
            throw ValidationException::withMessages([
                'event_name' => 'Database table is missing. Please run migrations first.',
            ]);
        }
    }

    /**
     * @param  Builder<DemoDay>|\Illuminate\Database\Eloquent\Builder  $query
     */
    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff') {
            $query->where('entered_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, DemoDay $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff' && (int) $row->entered_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, bool $requirePhotos): array
    {
        $rules = [
            'participating_incubatees' => ['nullable', 'array'],
            'participating_incubatees.*.cfa_submission_id' => ['nullable', 'integer', 'min:0'],
            'participating_incubatees.*.legacy_application_id' => ['nullable', 'integer', 'min:0'],
            'participating_incubatees.*.key' => ['nullable', 'string', 'max:64'],
            'participating_incubatees.*.name' => ['nullable', 'string', 'max:255'],
            'participating_incubatees.*.application_no' => ['nullable', 'string', 'max:64'],
            'event_date' => ['required', 'date'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', Rule::in(array_keys(DemoDayOptions::eventTypes()))],
            'investor_name' => ['nullable', 'string', 'max:255', 'required_if:event_type,investor_meet'],
            'event_type_other' => ['nullable', 'string', 'max:191', 'required_if:event_type,other'],
            'venue' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'string', Rule::in(array_keys(DemoDayOptions::modes()))],
            'summary' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'event_photos' => $requirePhotos
                ? ['required', 'array', 'min:1', 'max:5']
                : ['nullable', 'array', 'max:5'],
            'event_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];

        $validated = $request->validate($rules);

        $uploadErrors = $this->eventPhotosUploadErrors($request);
        if ($uploadErrors !== []) {
            throw ValidationException::withMessages($uploadErrors);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function resolveParticipatingIncubateesOrFail(array $validated): array
    {
        $entries = array_values(array_filter(
            (array) ($validated['participating_incubatees'] ?? []),
            fn ($row): bool => is_array($row),
        ));

        $resolved = $this->incubateeCatalog->resolveParticipatingEntries($entries);
        if ($resolved === []) {
            throw ValidationException::withMessages([
                'participating_incubatees' => 'Select at least one incubatee.',
            ]);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array<string, mixed>>  $participating
     * @return array<string, mixed>
     */
    private function payloadFromValidated(array $validated, array $participating, User $user): array
    {
        $eventType = (string) $validated['event_type'];
        $primary = $participating[0];
        $names = array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $participating,
        )));
        $participantCounts = IncubateeAttendeeCounts::fromSnapshots($participating);

        return [
            'event_date' => $validated['event_date'],
            'event_name' => trim((string) $validated['event_name']),
            'event_type' => $eventType,
            'investor_name' => $eventType === 'investor_meet'
                ? trim((string) ($validated['investor_name'] ?? ''))
                : null,
            'event_type_other' => $eventType === 'other'
                ? trim((string) ($validated['event_type_other'] ?? ''))
                : null,
            'venue' => trim((string) ($validated['venue'] ?? '')) ?: null,
            'mode' => trim((string) ($validated['mode'] ?? '')) ?: null,
            'male_participants' => $participantCounts['male'],
            'female_participants' => $participantCounts['female'],
            'cfa_submission_id' => $primary['cfa_submission_id'] ?? null,
            'legacy_application_id' => $primary['legacy_application_id'] ?? null,
            'district_id' => (int) ($primary['district_id'] ?? 0) ?: null,
            'incubatee_name' => implode('; ', $names),
            'application_no' => $primary['application_no'] ?? null,
            'participating_incubatees_json' => $participating,
            'outcome' => null,
            'summary' => trim((string) ($validated['summary'] ?? '')) ?: null,
            'remarks' => trim((string) ($validated['remarks'] ?? '')) ?: null,
            'entered_by_user_id' => (int) $user->id,
            'entered_by_name' => (string) $user->name,
        ];
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, array $filters, array $totals): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';
        $activeFy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first();

        return view('demo-days.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'currentRole' => (string) $user->role,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'totals' => $totals,
            'eventTypes' => DemoDayOptions::eventTypes(),
            'dashboardRoute' => $isAdmin ? 'admin.demo-days.dashboard' : 'spoc.demo-days.dashboard',
            'exportRoute' => $isAdmin ? 'admin.demo-days.export' : 'spoc.demo-days.export',
            'showRoute' => $isAdmin ? 'admin.demo-days.show' : 'spoc.demo-days.show',
            'attachmentRoute' => $isAdmin ? 'admin.demo-days.attachment' : 'spoc.demo-days.attachment',
            'createRoute' => FundingSchematicConvergenceAccess::canSubmit($user) ? 'spoc.demo-days.create' : null,
            'deliverableStats' => [
                'events_fy' => DemoDaysDeliverablesSupport::countEvents(
                    $activeFy?->starts_on ? \Carbon\Carbon::parse($activeFy->starts_on) : null,
                    $activeFy?->ends_on ? \Carbon\Carbon::parse($activeFy->ends_on) : null,
                ),
            ],
        ]);
    }
}
