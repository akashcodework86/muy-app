<?php

namespace App\Http\Controllers;

use App\Models\MediaCampaignAttachment;
use App\Models\MediaCampaignEntry;
use App\Models\User;
use App\Support\BrandingCommunicationAccess;
use App\Support\BrandingCommunicationOptions;
use App\Support\TodayOnlyDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaCampaignController extends Controller
{
    public function create(Request $request): View
    {
        $this->submitterOrAbort($request);

        return view('media-campaigns.form', [
            'user' => $request->user(),
            'migrationMissing' => ! Schema::hasTable('media_campaign_entries'),
            'storeRoute' => 'spoc.media-campaigns.store',
            'dashboardRoute' => 'spoc.media-campaigns.dashboard',
            'mediaTypes' => BrandingCommunicationOptions::mediaTypes(),
            'maxAttachments' => (int) config('branding_communication.media_campaign_max_attachments', 5),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->submitterOrAbort($request);

        if (! Schema::hasTable('media_campaign_entries')) {
            return redirect()
                ->route('spoc.media-campaigns.create')
                ->withErrors(['campaign_title' => 'Database table is missing. Please run migrations first.']);
        }

        $maxAttachments = (int) config('branding_communication.media_campaign_max_attachments', 5);

        $validated = $request->validate([
            'campaign_date' => TodayOnlyDate::rules(),
            'media_type' => ['required', 'string', Rule::in(array_keys(BrandingCommunicationOptions::mediaTypes()))],
            'channel_name' => ['required', 'string', 'max:255'],
            'coverage_area' => ['required', 'string', 'max:191'],
            'campaign_title' => ['required', 'string', 'max:255'],
            'ad_size_or_duration' => ['nullable', 'string', 'max:128'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
            'multimedia' => ['required', 'array', 'min:1', 'max:'.$maxAttachments],
            'multimedia.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp3,m4a,wav,mp4', 'max:20480'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'multimedia.required' => 'Upload at least one photo, audio, or video proof.',
            'multimedia.min' => 'Upload at least one photo, audio, or video proof.',
        ]);

        $document = $this->storeDocument($request->file('document'), 'media-campaigns/documents');
        $multimediaFiles = array_values(array_filter((array) $request->file('multimedia', [])));

        DB::transaction(function () use ($user, $validated, $document, $multimediaFiles): void {
            $entry = MediaCampaignEntry::query()->create([
                'campaign_date' => $validated['campaign_date'],
                'media_type' => (string) $validated['media_type'],
                'channel_name' => trim((string) $validated['channel_name']),
                'coverage_area' => trim((string) $validated['coverage_area']),
                'campaign_title' => trim((string) $validated['campaign_title']),
                'ad_size_or_duration' => $this->nullableTrim($validated['ad_size_or_duration'] ?? null),
                'document_disk' => $document['disk'],
                'document_path' => $document['path'],
                'document_original_name' => $document['name'],
                'remarks' => $this->nullableTrim($validated['remarks'] ?? null),
                'submitted_by_user_id' => (int) $user->id,
                'submitted_by_name' => (string) $user->name,
            ]);

            foreach ($this->storeMultimediaFiles($multimediaFiles) as $index => $item) {
                MediaCampaignAttachment::query()->create([
                    'media_campaign_entry_id' => (int) $entry->id,
                    'attachment_type' => $item['type'],
                    'disk' => $item['disk'],
                    'path' => $item['path'],
                    'original_name' => $item['original_name'],
                    'mime' => $item['mime'],
                    'size_bytes' => $item['size_bytes'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('spoc.media-campaigns.dashboard')
            ->with('status', 'Media campaign entry logged successfully.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        if (! Schema::hasTable('media_campaign_entries')) {
            return $this->dashboardView($request, collect(), true, ['q' => '', 'from' => '', 'to' => '', 'media_type' => '']);
        }

        $query = MediaCampaignEntry::query()->with(['submitter:id,name', 'attachments']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('campaign_title', 'like', $like)
                    ->orWhere('channel_name', 'like', $like)
                    ->orWhere('coverage_area', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhere('submitted_by_name', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('campaign_date', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('campaign_date', '<=', (string) $request->query('to'));
        }

        $typeFilter = trim((string) $request->query('media_type', ''));
        if ($typeFilter !== '' && array_key_exists($typeFilter, BrandingCommunicationOptions::mediaTypes())) {
            $query->where('media_type', $typeFilter);
        }

        $rows = $query
            ->orderByDesc('campaign_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'media_type' => $typeFilter,
        ]);
    }

    public function show(Request $request, MediaCampaignEntry $mediaCampaign): View
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $mediaCampaign);

        $mediaCampaign->loadMissing(['attachments']);

        $isAdmin = $user->role === 'state_admin';
        $routePrefix = $isAdmin ? 'admin' : 'spoc';

        return view('media-campaigns.show', [
            'row' => $mediaCampaign,
            'currentRole' => (string) $user->role,
            'dashboardRoute' => $routePrefix.'.media-campaigns.dashboard',
            'documentRoute' => $routePrefix.'.media-campaigns.document',
            'attachmentRoute' => $routePrefix.'.media-campaigns.attachment',
            'destroyRoute' => $isAdmin ? null : 'spoc.media-campaigns.destroy',
            'canDelete' => BrandingCommunicationAccess::canDelete($user, (int) $mediaCampaign->submitted_by_user_id),
            'mediaTypes' => BrandingCommunicationOptions::mediaTypes(),
        ]);
    }

    public function downloadDocument(Request $request, MediaCampaignEntry $mediaCampaign): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $mediaCampaign);

        if (! $mediaCampaign->hasDocument()) {
            abort(404);
        }

        return Storage::disk((string) $mediaCampaign->document_disk)
            ->download(
                (string) $mediaCampaign->document_path,
                (string) ($mediaCampaign->document_original_name ?: 'campaign-document')
            );
    }

    public function downloadAttachment(Request $request, MediaCampaignEntry $mediaCampaign): mixed
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $mediaCampaign);

        $attachmentId = (int) $request->query('attachment', 0);
        $attachment = $mediaCampaign->attachments()->where('id', $attachmentId)->first();
        if ($attachment === null) {
            abort(404);
        }

        $inline = (bool) $request->query('inline', false);
        $disk = Storage::disk((string) $attachment->disk);
        $filename = (string) ($attachment->original_name ?: 'attachment');

        if ($inline && str_starts_with((string) $attachment->mime, 'image/')) {
            return $disk->response((string) $attachment->path, $filename);
        }

        return $disk->download((string) $attachment->path, $filename);
    }

    public function destroy(Request $request, MediaCampaignEntry $mediaCampaign): RedirectResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canDelete($user, (int) $mediaCampaign->submitted_by_user_id), 403);

        $mediaCampaign->loadMissing(['attachments']);

        if ($mediaCampaign->hasDocument()) {
            Storage::disk((string) $mediaCampaign->document_disk)->delete((string) $mediaCampaign->document_path);
        }

        foreach ($mediaCampaign->attachments as $attachment) {
            Storage::disk((string) $attachment->disk)->delete((string) $attachment->path);
        }

        $mediaCampaign->delete();

        return redirect()
            ->route('spoc.media-campaigns.dashboard')
            ->with('status', 'Media campaign entry deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(BrandingCommunicationAccess::canViewDashboard($user), 403);

        $query = MediaCampaignEntry::query()->withCount('attachments');
        $this->scopeDashboardQuery($query, $user);

        $rows = $query->orderByDesc('campaign_date')->orderByDesc('id')->get();

        return $this->streamExportCsv($rows, 'media-campaigns-'.now()->format('Ymd_His').'.csv');
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
        $original = $file->getClientOriginalName() ?: 'document.pdf';
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

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array{disk: string, path: string, original_name: string, mime: string, size_bytes: int, type: string}>
     */
    private function storeMultimediaFiles(array $files): array
    {
        $disk = (string) config('filesystems.default', 'local');
        $items = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $original = (string) ($file->getClientOriginalName() ?: 'media');
            $mime = (string) ($file->getClientMimeType() ?? '');
            $type = $this->attachmentTypeForMime($mime, $file->getClientOriginalExtension());

            $path = $file->storeAs(
                'media-campaigns/multimedia/'.now()->format('Y/m'),
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

    private function attachmentTypeForMime(string $mime, ?string $extension): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        $ext = strtolower((string) $extension);

        return match ($ext) {
            'mp3', 'm4a', 'wav' => 'audio',
            'mp4' => 'video',
            default => 'image',
        };
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

        return view('media-campaigns.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'isAdminView' => $isAdmin,
            'filters' => $filters,
            'mediaTypes' => BrandingCommunicationOptions::mediaTypes(),
            'dashboardRoute' => $isAdmin ? 'admin.media-campaigns.dashboard' : 'spoc.media-campaigns.dashboard',
            'exportRoute' => $isAdmin ? 'admin.media-campaigns.export' : 'spoc.media-campaigns.export',
            'showRoute' => $isAdmin ? 'admin.media-campaigns.show' : 'spoc.media-campaigns.show',
            'attachmentRoute' => $isAdmin ? 'admin.media-campaigns.attachment' : 'spoc.media-campaigns.attachment',
            'createRoute' => BrandingCommunicationAccess::canSubmit($user) ? 'spoc.media-campaigns.create' : null,
        ]);
    }

    private function scopeDashboardQuery($query, User $user): void
    {
        if ($user->role === 'state_staff' && BrandingCommunicationAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord(User $user, MediaCampaignEntry $row): void
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
            fputcsv($out, ['#', 'Date', 'Title', 'Media type', 'Channel', 'Coverage', 'Multimedia files', 'Submitted by']);

            $serial = 1;
            foreach ($rows as $row) {
                if (! $row instanceof MediaCampaignEntry) {
                    continue;
                }

                fputcsv($out, [
                    $serial++,
                    $row->campaign_date?->format('Y-m-d') ?? '',
                    $row->campaign_title,
                    BrandingCommunicationOptions::mediaTypeLabel((string) $row->media_type),
                    $row->channel_name,
                    $row->coverage_area,
                    (int) ($row->attachments_count ?? $row->attachments()->count()),
                    $row->submitted_by_name,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
