<?php

namespace App\Http\Controllers;

use App\Models\PitchDeckPreparation;
use App\Services\PitchDeckIncubateeCatalogService;
use App\Services\PitchDeckUnifiedDashboardService;
use App\Support\PitchDeckCombinedDeliverablesSupport;
use App\Support\PitchDeckPreparationAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PitchDeckPreparationController extends Controller
{
    public function __construct(
        private readonly PitchDeckIncubateeCatalogService $incubateeCatalog,
        private readonly PitchDeckUnifiedDashboardService $unifiedDashboard,
    ) {}

    public function create(Request $request): View
    {
        abort_unless(PitchDeckPreparationAccess::canSubmit($request->user()), 403);

        return view('pitch-deck-preparations.form', [
            'user' => $request->user(),
            'migrationMissing' => ! Schema::hasTable('pitch_deck_preparations'),
            'storeRoute' => 'spoc.pitch-deck-preparations.store',
            'dashboardRoute' => 'spoc.pitch-deck-preparations.dashboard',
            'searchRoute' => 'spoc.pitch-deck-preparations.incubatees.search',
            'supportModes' => config('pitch_deck_preparations.support_modes', []),
            'row' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canSubmit($user), 403);

        if (! Schema::hasTable('pitch_deck_preparations')) {
            return redirect()
                ->route('spoc.pitch-deck-preparations.create')
                ->withErrors(['incubatee_key' => 'Table not found. Please run migrations first.']);
        }

        $validated = $this->validateSubmission($request, requireFile: true);
        $snapshot = $this->resolveIncubateeOrFail($validated);
        $this->assertNotDuplicate((int) ($validated['cfa_submission_id'] ?? 0), (int) ($validated['legacy_application_id'] ?? 0));

        $fileMeta = $this->storeDeckFile($request->file('deck_file'));

        PitchDeckPreparation::query()->create([
            'cfa_submission_id' => (int) ($validated['cfa_submission_id'] ?? 0) ?: null,
            'legacy_application_id' => (int) ($validated['legacy_application_id'] ?? 0) ?: null,
            'district_id' => (int) $snapshot['district_id'],
            'incubatee_name' => (string) $snapshot['name'],
            'application_no' => $snapshot['application_no'],
            'entered_by_user_id' => (int) $user->id,
            'entered_by_name' => (string) $user->name,
            'prepared_on' => $validated['prepared_on'],
            'prepared_for' => $this->nullableTrim($validated['prepared_for'] ?? null),
            'support_mode' => $this->nullableTrim($validated['support_mode'] ?? null),
            'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
            'deck_file_disk' => $fileMeta['disk'],
            'deck_file_path' => $fileMeta['path'],
            'deck_file_name' => $fileMeta['name'],
        ]);

        return redirect()
            ->route('spoc.pitch-deck-preparations.dashboard')
            ->with('status', 'Pitch deck preparation (MIS 8.3) saved.');
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        abort_unless(PitchDeckPreparationAccess::canSubmit($request->user()), 403);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        return response()->json([
            'results' => $this->incubateeCatalog->search((string) $validated['q']),
        ]);
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canViewDashboard($user), 403);

        $isAdminView = $user->role === 'state_admin';
        $filters = $this->dashboardFiltersFromRequest($request, $user);

        if (! Schema::hasTable('pitch_deck_preparations')) {
            return view('pitch-deck-preparations.dashboard', [
                'rows' => collect(),
                'migrationMissing' => true,
                'isPaginated' => false,
                'isAdminView' => $isAdminView,
                'canSubmit' => PitchDeckPreparationAccess::canSubmit($user),
                'totals' => ['total' => 0, 'services' => 0, 'state_team' => 0],
                'filters' => $filters,
            ]);
        }

        $dashboard = $this->unifiedDashboard->paginatedDashboard(
            $filters,
            25,
            includeServices: true,
        );

        return view('pitch-deck-preparations.dashboard', [
            'rows' => $dashboard['rows'],
            'incubateeProfiles' => $dashboard['incubateeProfiles'],
            'migrationMissing' => false,
            'isPaginated' => true,
            'isAdminView' => $isAdminView,
            'currentRole' => (string) $user->role,
            'canSubmit' => PitchDeckPreparationAccess::canSubmit($user),
            'supportModes' => config('pitch_deck_preparations.support_modes', []),
            'filters' => $filters,
            'totals' => $dashboard['totals'],
            'exportRoute' => $this->exportRouteForRole((string) $user->role),
            'districts' => \App\Models\District::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filledByOptions' => [
                '' => 'All sources',
                PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES => PitchDeckCombinedDeliverablesSupport::LABEL_SERVICES,
                PitchDeckCombinedDeliverablesSupport::CHANNEL_STATE_TEAM => PitchDeckCombinedDeliverablesSupport::LABEL_STATE_TEAM,
            ],
        ]);
    }

    public function show(Request $request, PitchDeckPreparation $pitchDeckPreparation): View
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canViewDashboard($user), 403);

        if ($user->role !== 'state_admin' && (int) $pitchDeckPreparation->entered_by_user_id !== (int) $user->id) {
            abort(403);
        }

        $pitchDeckPreparation->loadMissing('district.hub:id,name');

        return view('pitch-deck-preparations.show', [
            'row' => $pitchDeckPreparation,
            'incubateeProfile' => $this->incubateeCatalog->profileForPreparation($pitchDeckPreparation),
            'currentRole' => (string) $user->role,
            'canEdit' => PitchDeckPreparationAccess::canEdit($user, $pitchDeckPreparation),
            'canDelete' => PitchDeckPreparationAccess::canDelete($user, $pitchDeckPreparation),
        ]);
    }

    public function edit(Request $request, PitchDeckPreparation $pitchDeckPreparation): View
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canEdit($user, $pitchDeckPreparation), 403);

        return view('pitch-deck-preparations.form', [
            'user' => $user,
            'row' => $pitchDeckPreparation,
            'migrationMissing' => ! Schema::hasTable('pitch_deck_preparations'),
            'storeRoute' => 'spoc.pitch-deck-preparations.update',
            'dashboardRoute' => 'spoc.pitch-deck-preparations.dashboard',
            'searchRoute' => 'spoc.pitch-deck-preparations.incubatees.search',
            'supportModes' => config('pitch_deck_preparations.support_modes', []),
        ]);
    }

    public function update(Request $request, PitchDeckPreparation $pitchDeckPreparation): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canEdit($user, $pitchDeckPreparation), 403);

        $validated = $this->validateSubmission($request, requireFile: false);
        $snapshot = $this->resolveIncubateeOrFail($validated);
        $this->assertNotDuplicate(
            (int) ($validated['cfa_submission_id'] ?? 0),
            (int) ($validated['legacy_application_id'] ?? 0),
            ignoreId: (int) $pitchDeckPreparation->id,
        );

        $updates = [
            'cfa_submission_id' => (int) ($validated['cfa_submission_id'] ?? 0) ?: null,
            'legacy_application_id' => (int) ($validated['legacy_application_id'] ?? 0) ?: null,
            'district_id' => (int) $snapshot['district_id'],
            'incubatee_name' => (string) $snapshot['name'],
            'application_no' => $snapshot['application_no'],
            'prepared_on' => $validated['prepared_on'],
            'prepared_for' => $this->nullableTrim($validated['prepared_for'] ?? null),
            'support_mode' => $this->nullableTrim($validated['support_mode'] ?? null),
            'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
        ];

        if ($request->hasFile('deck_file')) {
            $this->deleteStoredFile($pitchDeckPreparation);
            $fileMeta = $this->storeDeckFile($request->file('deck_file'));
            $updates['deck_file_disk'] = $fileMeta['disk'];
            $updates['deck_file_path'] = $fileMeta['path'];
            $updates['deck_file_name'] = $fileMeta['name'];
        }

        $pitchDeckPreparation->update($updates);

        return redirect()
            ->route('spoc.pitch-deck-preparations.dashboard')
            ->with('status', 'Pitch deck entry updated.');
    }

    public function destroy(Request $request, PitchDeckPreparation $pitchDeckPreparation): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canDelete($user, $pitchDeckPreparation), 403);

        $this->deleteStoredFile($pitchDeckPreparation);
        $pitchDeckPreparation->delete();

        $route = $user->role === 'state_admin'
            ? 'admin.pitch-deck-preparations.dashboard'
            : 'spoc.pitch-deck-preparations.dashboard';

        return redirect()
            ->route($route)
            ->with('status', 'Pitch deck entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(PitchDeckPreparationAccess::canViewDashboard($user), 403);

        $isAdminView = $user->role === 'state_admin';
        $filters = $this->dashboardFiltersFromRequest($request, $user);
        $rows = $this->unifiedDashboard->exportRows($filters, includeServices: true);
        $filename = 'pitch-deck-preparations-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Source', 'Filled by', 'ID', 'Incubatee', 'Application no', 'Reference',
                'Phone', 'District', 'Onboarding', 'Prepared on', 'Prepared for',
                'Support / status', 'Entered by',
            ]);
            foreach ($rows as $row) {
                $profile = $row['incubatee_profile'] ?? [];
                fputcsv($out, [
                    $row['filled_by_label'] ?? '',
                    $row['filled_by_name'] ?? '',
                    $row['id'] ?? '',
                    $row['incubatee_name'] ?? '',
                    $row['application_no'] ?? '',
                    $row['reference_number'] ?? '',
                    $row['phone'] ?? ($profile['phone'] ?? ''),
                    $row['district_name'] ?? '',
                    ! empty($profile['is_onboarded']) ? 'Onboarded' : ($profile['onboarding_status'] ?? ''),
                    $row['prepared_on_display'] ?? '',
                    $row['prepared_for'] ?? '',
                    $row['support_mode'] ?? '',
                    $row['entered_by_name'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadDeck(Request $request, PitchDeckPreparation $pitchDeckPreparation): StreamedResponse
    {
        $user = $request->user();
        $this->authorizeDeckAccess($user, $pitchDeckPreparation);

        $disk = (string) ($pitchDeckPreparation->deck_file_disk ?: 'local');
        $path = (string) $pitchDeckPreparation->deck_file_path;
        $filename = (string) $pitchDeckPreparation->deck_file_name;
        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        if ($request->boolean('inline')) {
            return $this->streamDeckInline($disk, $path, $filename);
        }

        return Storage::disk($disk)->download($path, $filename);
    }

    private function authorizeDeckAccess(?\App\Models\User $user, PitchDeckPreparation $pitchDeckPreparation): void
    {
        abort_unless(PitchDeckPreparationAccess::canViewDashboard($user), 403);

        if ($user?->role !== 'state_admin' && (int) $pitchDeckPreparation->entered_by_user_id !== (int) $user?->id) {
            abort(403);
        }
    }

    private function streamDeckInline(string $disk, string $path, string $filename): StreamedResponse
    {
        $storage = Storage::disk($disk);
        $stream = $storage->readStream($path);
        abort_unless(is_resource($stream), 404);

        $safeName = str_replace('"', '', $filename !== '' ? $filename : 'pitch-deck');
        $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => $storage->mimeType($path) ?: 'application/octet-stream',
        };

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, bool $requireFile): array
    {
        $modes = array_keys(config('pitch_deck_preparations.support_modes', []));

        return $request->validate([
            'cfa_submission_id' => ['nullable', 'integer', 'min:0'],
            'legacy_application_id' => ['nullable', 'integer', 'min:0'],
            'prepared_on' => ['required', 'date'],
            'prepared_for' => ['nullable', 'string', 'max:191'],
            'support_mode' => ['nullable', 'string', Rule::in($modes)],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'deck_file' => array_filter([
                $requireFile ? 'required' : 'nullable',
                'file',
                'mimes:pdf,ppt,pptx',
                'max:20480',
            ]),
        ], [
            'deck_file.required' => 'Upload the pitch deck file (PDF or PPT).',
            'deck_file.mimes' => 'Pitch deck must be PDF or PowerPoint (PPT/PPTX).',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, application_no: ?string, district_id: int}
     */
    private function resolveIncubateeOrFail(array $validated): array
    {
        $cfaId = (int) ($validated['cfa_submission_id'] ?? 0);
        $legacyId = (int) ($validated['legacy_application_id'] ?? 0);

        if (($cfaId > 0) === ($legacyId > 0)) {
            throw ValidationException::withMessages([
                'incubatee_key' => 'Select exactly one incubatee (Phase 3 CFA or Phase 2 legacy).',
            ]);
        }

        $snapshot = $this->incubateeCatalog->resolveSelection($cfaId, $legacyId);
        if ($snapshot === null || (int) ($snapshot['district_id'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'incubatee_key' => 'Invalid incubatee selection.',
            ]);
        }

        return $snapshot;
    }

    private function assertNotDuplicate(int $cfaId, int $legacyId, int $ignoreId = 0): void
    {
        $exists = PitchDeckPreparation::query()
            ->when($ignoreId > 0, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($cfaId, $legacyId): void {
                if ($cfaId > 0) {
                    $q->orWhere('cfa_submission_id', $cfaId);
                }
                if ($legacyId > 0) {
                    $q->orWhere('legacy_application_id', $legacyId);
                }
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'incubatee_key' => 'A pitch deck is already recorded for this incubatee.',
            ]);
        }
    }

    /**
     * @return array{q: string, from: string, to: string, district_id: int, filled_by: string, state_team_user_id?: int}
     */
    private function dashboardFiltersFromRequest(Request $request, \App\Models\User $user): array
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'district_id' => (int) $request->query('district_id', 0),
            'filled_by' => (string) $request->query('filled_by', ''),
        ];

        return $filters;
    }

    /**
     * @param  Builder<PitchDeckPreparation>  $query
     */
    private function applyListFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('prepared_for', 'like', $like)
                    ->orWhere('entered_by_name', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhereHas('district', fn ($dq) => $dq->where('name', 'like', $like));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('prepared_on', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('prepared_on', '<=', (string) $request->query('to'));
        }
        if ($request->filled('district_id')) {
            $query->where('district_id', (int) $request->query('district_id'));
        }
    }

    /**
     * @return array{disk: string, path: string, name: string}
     */
    private function storeDeckFile(UploadedFile $file): array
    {
        $original = $file->getClientOriginalName() ?: 'pitch-deck.pdf';
        $safe = Str::slug(pathinfo($original, PATHINFO_FILENAME));
        $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $filename = ($safe !== '' ? $safe : 'pitch-deck').'_'.Str::random(8).'.'.$ext;
        $path = $file->storeAs('pitch-deck-preparations', $filename);

        return [
            'disk' => 'local',
            'path' => (string) $path,
            'name' => $original,
        ];
    }

    private function deleteStoredFile(PitchDeckPreparation $row): void
    {
        $disk = (string) ($row->deck_file_disk ?: 'local');
        $path = (string) $row->deck_file_path;
        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function exportRouteForRole(string $role): string
    {
        return match ($role) {
            'state_admin' => 'admin.pitch-deck-preparations.export',
            default => 'spoc.pitch-deck-preparations.export',
        };
    }
}
