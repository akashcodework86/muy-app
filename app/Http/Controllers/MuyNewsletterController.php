<?php

namespace App\Http\Controllers;

use App\Models\MuyNewsletterEntry;
use App\Models\User;
use App\Support\BrandingCommunicationAccess;
use App\Support\BrandingCommunicationOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MuyNewsletterController extends Controller
{
    public function create(Request $request): View
    {
        $this->submitterOrAbort($request);

        return view('muy-newsletters.form', [
            'user' => $request->user(),
            'migrationMissing' => ! Schema::hasTable('muy_newsletter_entries'),
            'storeRoute' => 'spoc.muy-newsletters.store',
            'dashboardRoute' => 'spoc.muy-newsletters.dashboard',
            'distributionModes' => BrandingCommunicationOptions::distributionModes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('muy_newsletter_entries')) {
            return redirect()
                ->route('spoc.muy-newsletters.create')
                ->withErrors(['title' => 'Database table is missing. Please run migrations first.']);
        }

        $validated = $request->validate([
            'issue_date' => ['required', 'date'],
            'issue_edition' => ['required', 'string', 'max:128'],
            'title' => ['required', 'string', 'max:255'],
            'distribution_mode' => ['required', 'string', Rule::in(array_keys(BrandingCommunicationOptions::distributionModes()))],
            'newsletter_url' => ['nullable', 'url', 'max:2048', 'required_without:document'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:20480', 'required_without:newsletter_url'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'document.required_without' => 'Upload the newsletter PDF or provide a link.',
            'newsletter_url.required_without' => 'Provide a newsletter link or upload a PDF.',
        ]);

        $documentMeta = null;
        if ($request->file('document') instanceof UploadedFile) {
            $documentMeta = $this->storeDocument($request->file('document'), 'muy-newsletters');
        }

        MuyNewsletterEntry::query()->create([
            'issue_date' => $validated['issue_date'],
            'issue_edition' => trim((string) $validated['issue_edition']),
            'title' => trim((string) $validated['title']),
            'distribution_mode' => (string) $validated['distribution_mode'],
            'newsletter_url' => $this->nullableTrim($validated['newsletter_url'] ?? null),
            'document_disk' => $documentMeta['disk'] ?? null,
            'document_path' => $documentMeta['path'] ?? null,
            'document_original_name' => $documentMeta['name'] ?? null,
            'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
        ]);

        return redirect()
            ->route('spoc.muy-newsletters.dashboard')
            ->with('status', 'MUY newsletter entry logged successfully.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('muy_newsletter_entries')) {
            return $this->dashboardView($request, collect(), true, ['q' => '', 'from' => '', 'to' => '']);
        }

        $query = MuyNewsletterEntry::query()->with(['submitter:id,name']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('issue_edition', 'like', $like)
                    ->orWhere('newsletter_url', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('issue_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('issue_date', '<=', (string) $request->query('to'));
        }

        $rows = $query
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ]);
    }

    public function show(Request $request, MuyNewsletterEntry $muyNewsletter): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $muyNewsletter);

        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'spoc';

        return view('muy-newsletters.show', [
            'row' => $muyNewsletter,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $routePrefix.'.muy-newsletters.dashboard',
            'documentRoute' => $routePrefix.'.muy-newsletters.document',
            'destroyRoute' => $isAdmin ? null : 'spoc.muy-newsletters.destroy',
            'canDelete' => BrandingCommunicationAccess::canDelete($user, (int) $muyNewsletter->submitted_by_user_id),
            'distributionModes' => BrandingCommunicationOptions::distributionModes(),
        ]);
    }

    public function downloadDocument(Request $request, MuyNewsletterEntry $muyNewsletter): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $muyNewsletter);

        if (! $muyNewsletter->hasDocument()) {
            abort(404);
        }

        return Storage::disk((string) $muyNewsletter->document_disk)
            ->download(
                (string) $muyNewsletter->document_path,
                (string) ($muyNewsletter->document_original_name ?: 'newsletter.pdf')
            );
    }

    public function destroy(Request $request, MuyNewsletterEntry $muyNewsletter): RedirectResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canDelete($user, (int) $muyNewsletter->submitted_by_user_id), 403);

        if ($muyNewsletter->hasDocument()) {
            Storage::disk((string) $muyNewsletter->document_disk)->delete((string) $muyNewsletter->document_path);
        }

        $muyNewsletter->delete();

        return redirect()
            ->route('spoc.muy-newsletters.dashboard')
            ->with('status', 'Newsletter entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        $query = MuyNewsletterEntry::query();
        $this->scopeDashboardQuery($query, $user);

        $rows = $query->orderByDesc('issue_date')->orderByDesc('id')->get();

        return $this->streamExportCsv($rows, 'muy-newsletters-'.now()->format('Ymd_His').'.csv');
    }

    private function submitterOrAbort(Request $request): User
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canSubmit($user), 403);

        return $user;
    }

    /**
     * @return array{disk: string, path: string, name: string}
     */
    private function storeDocument(UploadedFile $file, string $folder): array
    {
        $original = $file->getClientOriginalName() ?: 'newsletter.pdf';
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
        ];
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

        return view('muy-newsletters.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'dashboardRoute' => $isAdmin ? 'admin.muy-newsletters.dashboard' : 'spoc.muy-newsletters.dashboard',
            'exportRoute' => $isAdmin ? 'admin.muy-newsletters.export' : 'spoc.muy-newsletters.export',
            'showRoute' => $isAdmin ? 'admin.muy-newsletters.show' : 'spoc.muy-newsletters.show',
            'createRoute' => BrandingCommunicationAccess::canSubmit($user) ? 'spoc.muy-newsletters.create' : null,
        ]);
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, MuyNewsletterEntry $row): void
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
            fputcsv($out, ['#', 'Issue date', 'Edition', 'Title', 'Distribution', 'Link', 'Submitted by']);

            $serial = 1;
            foreach ($rows as $row) {
                if (! $row instanceof MuyNewsletterEntry) {
                    continue;
                }

                fputcsv($out, [
                    $serial++,
                    $row->issue_date?->format('Y-m-d') ?? '',
                    $row->issue_edition,
                    $row->title,
                    BrandingCommunicationOptions::distributionModeLabel((string) $row->distribution_mode),
                    $row->newsletter_url ?? '',
                    $row->submitted_by_name,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
