<?php

namespace App\Http\Controllers;

use App\Models\CaseStudyEntry;
use App\Models\CaseStudyEntryAttachment;
use App\Models\User;
use App\Services\PitchDeckIncubateeCatalogService;
use App\Support\BrandingCommunicationAccess;
use App\Support\BrandingCommunicationOptions;
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

class CaseStudyEntryController extends Controller
{
    public function __construct(
        private readonly PitchDeckIncubateeCatalogService $incubateeCatalog,
    ) {}

    public function create(Request $request): View
    {
        $this->submitterOrAbort($request);

        return view('case-study-entries.form', [
            'user' => $request->user(),
            'migrationMissing' => ! Schema::hasTable('case_study_entries'),
            'storeRoute' => 'spoc.case-study-entries.store',
            'dashboardRoute' => 'spoc.case-study-entries.dashboard',
            'searchRoute' => 'spoc.case-study-entries.incubatees.search',
            'storyTypes' => BrandingCommunicationOptions::storyTypes(),
            'maxAttachments' => (int) config('branding_communication.case_study_max_attachments', 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('case_study_entries')) {
            return redirect()
                ->route('spoc.case-study-entries.create')
                ->withErrors(['story_title' => 'Database table is missing. Please run migrations first.']);
        }

        $maxAttachments = (int) config('branding_communication.case_study_max_attachments', 10);

        $validated = $request->validate([
            'story_title' => ['required', 'string', 'max:255'],
            'story_type' => ['required', 'string', Rule::in(array_keys(BrandingCommunicationOptions::storyTypes()))],
            'cfa_submission_id' => ['nullable', 'integer', 'min:0'],
            'legacy_application_id' => ['nullable', 'integer', 'min:0'],
            'story_date' => ['required', 'date'],
            'documents' => ['required', 'array', 'min:1', 'max:'.$maxAttachments],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'documents.required' => 'Upload at least one document or image.',
            'documents.min' => 'Upload at least one document or image.',
        ]);

        $snapshot = $this->resolveIncubateeOrFail($validated);
        $files = array_values(array_filter(
            (array) $request->file('documents', []),
            fn ($file): bool => $file instanceof UploadedFile
        ));

        if ($files === []) {
            throw ValidationException::withMessages([
                'documents' => 'Upload at least one document or image.',
            ]);
        }

        $storedFiles = $this->storeDocumentFiles($files);
        $primary = $storedFiles[0];

        DB::transaction(function () use ($user, $validated, $snapshot, $storedFiles, $primary): void {
            $entry = CaseStudyEntry::query()->create([
                'story_title' => trim((string) $validated['story_title']),
                'story_type' => (string) $validated['story_type'],
                'cfa_submission_id' => (int) ($validated['cfa_submission_id'] ?? 0) ?: null,
                'legacy_application_id' => (int) ($validated['legacy_application_id'] ?? 0) ?: null,
                'incubatee_key' => $snapshot['key'] ?? null,
                'incubatee_name' => (string) $snapshot['name'],
                'application_no' => $snapshot['application_no'] ?? null,
                'story_date' => $validated['story_date'],
                'document_disk' => $primary['disk'],
                'document_path' => $primary['path'],
                'document_original_name' => $primary['original_name'],
                'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
                'submitted_by_user_id' => (int) $user->id,
                'submitted_by_name' => (string) $user->name,
            ]);

            if (Schema::hasTable('case_study_entry_attachments')) {
                foreach ($storedFiles as $index => $item) {
                    CaseStudyEntryAttachment::query()->create([
                        'case_study_entry_id' => (int) $entry->id,
                        'attachment_type' => $item['type'],
                        'disk' => $item['disk'],
                        'path' => $item['path'],
                        'original_name' => $item['original_name'],
                        'mime' => $item['mime'],
                        'size_bytes' => $item['size_bytes'],
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        return redirect()
            ->route('spoc.case-study-entries.dashboard')
            ->with('status', 'Case study / testimonial logged successfully.');
    }

    public function searchIncubatees(Request $request): JsonResponse
    {
        $this->submitterOrAbort($request);

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
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('case_study_entries')) {
            return $this->dashboardView($request, collect(), true, ['q' => '', 'from' => '', 'to' => '', 'story_type' => '']);
        }

        $query = CaseStudyEntry::query()->with(['submitter:id,name']);
        if (Schema::hasTable('case_study_entry_attachments')) {
            $query->withCount('attachments');
        }
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('story_title', 'like', $like)
                    ->orWhere('incubatee_name', 'like', $like)
                    ->orWhere('application_no', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('story_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('story_date', '<=', (string) $request->query('to'));
        }

        $typeFilter = trim((string) $request->query('story_type', ''));
        if ($typeFilter !== '' && array_key_exists($typeFilter, BrandingCommunicationOptions::storyTypes())) {
            $query->where('story_type', $typeFilter);
        }

        $rows = $query
            ->orderByDesc('story_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'story_type' => $typeFilter,
        ]);
    }

    public function show(Request $request, CaseStudyEntry $caseStudyEntry): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $caseStudyEntry);

        if (Schema::hasTable('case_study_entry_attachments')) {
            $caseStudyEntry->loadMissing(['attachments']);
        }

        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'spoc';

        return view('case-study-entries.show', [
            'row' => $caseStudyEntry,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $routePrefix.'.case-study-entries.dashboard',
            'documentRoute' => $routePrefix.'.case-study-entries.document',
            'attachmentRoute' => $routePrefix.'.case-study-entries.attachment',
            'destroyRoute' => $isAdmin ? null : 'spoc.case-study-entries.destroy',
            'canDelete' => BrandingCommunicationAccess::canDelete($user, (int) $caseStudyEntry->submitted_by_user_id),
            'storyTypes' => BrandingCommunicationOptions::storyTypes(),
        ]);
    }

    public function downloadDocument(Request $request, CaseStudyEntry $caseStudyEntry): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $caseStudyEntry);

        if (! $caseStudyEntry->hasDocument()) {
            abort(404);
        }

        $inline = (bool) $request->query('inline', false);
        $disk = Storage::disk((string) $caseStudyEntry->document_disk);
        $filename = (string) ($caseStudyEntry->document_original_name ?: 'case-study-document');
        $path = (string) $caseStudyEntry->document_path;

        if ($inline && $this->pathLooksLikeImage($path, $filename)) {
            return $disk->response($path, $filename);
        }

        return $disk->download($path, $filename);
    }

    public function downloadAttachment(Request $request, CaseStudyEntry $caseStudyEntry): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $caseStudyEntry);

        if (! Schema::hasTable('case_study_entry_attachments')) {
            abort(404);
        }

        $attachmentId = (int) $request->query('attachment', 0);
        $attachment = $caseStudyEntry->attachments()->where('id', $attachmentId)->first();
        if ($attachment === null) {
            abort(404);
        }

        $inline = (bool) $request->query('inline', false);
        $disk = Storage::disk((string) $attachment->disk);
        $filename = (string) ($attachment->original_name ?: 'attachment');

        if ($inline && $attachment->isImage()) {
            return $disk->response((string) $attachment->path, $filename);
        }

        return $disk->download((string) $attachment->path, $filename);
    }

    public function destroy(Request $request, CaseStudyEntry $caseStudyEntry): RedirectResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canDelete($user, (int) $caseStudyEntry->submitted_by_user_id), 403);

        $deletedPaths = [];

        if (Schema::hasTable('case_study_entry_attachments')) {
            $caseStudyEntry->loadMissing(['attachments']);
            foreach ($caseStudyEntry->attachments as $attachment) {
                $key = (string) $attachment->disk.'|'.(string) $attachment->path;
                if (! isset($deletedPaths[$key])) {
                    Storage::disk((string) $attachment->disk)->delete((string) $attachment->path);
                    $deletedPaths[$key] = true;
                }
            }
        }

        if ($caseStudyEntry->hasDocument()) {
            $key = (string) $caseStudyEntry->document_disk.'|'.(string) $caseStudyEntry->document_path;
            if (! isset($deletedPaths[$key])) {
                Storage::disk((string) $caseStudyEntry->document_disk)->delete((string) $caseStudyEntry->document_path);
            }
        }

        $caseStudyEntry->delete();

        return redirect()
            ->route('spoc.case-study-entries.dashboard')
            ->with('status', 'Case study entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        $query = CaseStudyEntry::query();
        $this->scopeDashboardQuery($query, $user);

        $rows = $query->orderByDesc('story_date')->orderByDesc('id')->get();

        return $this->streamExportCsv($rows, 'case-study-entries-'.now()->format('Ymd_His').'.csv');
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canSubmit($user), 403);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, application_no: ?string, key?: string}
     */
    private function resolveIncubateeOrFail(array $validated): array
    {
        $cfaId = (int) ($validated['cfa_submission_id'] ?? 0);
        $legacyId = (int) ($validated['legacy_application_id'] ?? 0);

        if (($cfaId > 0) === ($legacyId > 0)) {
            throw ValidationException::withMessages([
                'incubatee_key' => 'Select exactly one incubatee from search results.',
            ]);
        }

        $snapshot = $this->incubateeCatalog->resolveSelection($cfaId, $legacyId);
        if ($snapshot === null) {
            throw ValidationException::withMessages([
                'incubatee_key' => 'Invalid incubatee selection.',
            ]);
        }

        return $snapshot;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{disk: string, path: string, original_name: string, mime: string, size_bytes: int, type: string}>
     */
    private function storeDocumentFiles(array $files): array
    {
        $disk = (string) config('filesystems.default', 'local');
        $items = [];

        foreach ($files as $file) {
            $original = (string) ($file->getClientOriginalName() ?: 'document.pdf');
            $mime = (string) ($file->getClientMimeType() ?? '');
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $type = $this->attachmentTypeForMime($mime, $extension);

            $path = $file->storeAs(
                'case-study-entries/'.now()->format('Y/m'),
                Str::uuid()->toString().'_'.Str::slug(pathinfo($original, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(),
                $disk,
            );

            $items[] = [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $original,
                'mime' => $mime,
                'size_bytes' => (int) ($file->getSize() ?? 0),
                'type' => $type,
            ];
        }

        return $items;
    }

    private function attachmentTypeForMime(string $mime, string $extension): string
    {
        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'image';
        }

        return 'document';
    }

    private function pathLooksLikeImage(string $path, string $filename): bool
    {
        $ext = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
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

        return view('case-study-entries.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'storyTypes' => BrandingCommunicationOptions::storyTypes(),
            'dashboardRoute' => $isAdmin ? 'admin.case-study-entries.dashboard' : 'spoc.case-study-entries.dashboard',
            'exportRoute' => $isAdmin ? 'admin.case-study-entries.export' : 'spoc.case-study-entries.export',
            'showRoute' => $isAdmin ? 'admin.case-study-entries.show' : 'spoc.case-study-entries.show',
            'createRoute' => BrandingCommunicationAccess::canSubmit($user) ? 'spoc.case-study-entries.create' : null,
        ]);
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, CaseStudyEntry $row): void
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

    private function streamExportCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['#', 'Date', 'Title', 'Type', 'Incubatee', 'Application no.', 'Submitted by', 'Remarks']);

            $serial = 1;
            foreach ($rows as $row) {
                if (! $row instanceof CaseStudyEntry) {
                    continue;
                }

                fputcsv($out, [
                    $serial++,
                    $row->story_date?->format('Y-m-d') ?? '',
                    $row->story_title,
                    BrandingCommunicationOptions::storyTypeLabel((string) $row->story_type),
                    $row->incubatee_name,
                    $row->application_no ?? '',
                    $row->submitted_by_name,
                    $row->remarks ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
