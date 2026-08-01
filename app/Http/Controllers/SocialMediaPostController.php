<?php

namespace App\Http\Controllers;

use App\Models\SocialMediaPost;
use App\Services\SocialMediaPostPreviewService;
use App\Support\SocialMediaPostAccess;
use App\Support\SocialMediaPostPlatforms;
use App\Support\TodayOnlyDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialMediaPostController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canSubmit($user), 403);

        return view('social-media-posts.form', [
            'user' => $user,
            'migrationMissing' => ! $this->socialMediaPostsReady(),
            'storeRoute' => 'spoc.social-media-posts.store',
            'dashboardRoute' => 'spoc.social-media-posts.dashboard',
            'previewUrl' => Route::has('spoc.social-media-posts.preview')
                ? route('spoc.social-media-posts.preview')
                : null,
            'platformOptions' => SocialMediaPostPlatforms::options(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canSubmit($user) || $user->role === 'state_admin', 403);

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        return response()->json(
            $this->withProxiedThumbnail($request, $this->previewService()->resolve((string) $validated['url']))
        );
    }

    public function thumbnail(Request $request): Response
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canSubmit($user) || $user->role === 'state_admin', 403);

        $encoded = (string) $request->query('src', '');
        $rawUrl = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($rawUrl) || $rawUrl === '' || ! $this->previewService()->shouldProxyThumbnail($rawUrl)) {
            abort(400, 'Invalid thumbnail URL.');
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => 'https://www.instagram.com/',
                ])
                ->get($rawUrl);
        } catch (\Throwable) {
            abort(502, 'Could not fetch thumbnail.');
        }

        if (! $response->successful()) {
            abort(502, 'Could not fetch thumbnail.');
        }

        $contentType = $response->header('Content-Type') ?: 'image/jpeg';

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canSubmit($user), 403);

        if (! $this->socialMediaPostsReady()) {
            return redirect()
                ->route('spoc.social-media-posts.create')
                ->withErrors(['posted_on' => 'Social media posts database is not ready. Please run migrations first.']);
        }

        $validated = $request->validate([
            'posted_on' => TodayOnlyDate::rules(),
            'post_url' => ['required', 'url', 'max:2048'],
            'posted_platforms' => ['required', 'array', 'min:1'],
            'posted_platforms.*' => ['string', Rule::in(array_keys(SocialMediaPostPlatforms::options()))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $postUrl = trim((string) $validated['post_url']);
        $previewMeta = $this->previewMetaForUrl($postUrl);
        $postedPlatforms = SocialMediaPostPlatforms::normalize($validated['posted_platforms']);

        SocialMediaPost::query()->create([
            'submitted_by_user_id' => (int) $user->id,
            'submitted_by_name' => (string) $user->name,
            'posted_on' => $validated['posted_on'],
            'post_url' => $postUrl,
            'posted_platforms' => $postedPlatforms,
            'platform' => $previewMeta['platform'],
            'thumbnail_url' => $previewMeta['thumbnail_url'],
            'preview_title' => $previewMeta['preview_title'],
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ]);

        return redirect()
            ->route('spoc.social-media-posts.dashboard', ['view' => 'posts'])
            ->with('status', 'Social media post logged successfully.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canViewDashboard($user), 403);

        $viewMode = (string) $request->query('view', 'posts');
        if (! in_array($viewMode, ['list', 'posts'], true)) {
            $viewMode = 'posts';
        }

        if (! $this->socialMediaPostsReady()) {
            return $this->dashboardView($request, collect(), true, [
                'q' => '',
                'from' => '',
                'to' => '',
                'view' => $viewMode,
            ]);
        }

        try {
            $query = SocialMediaPost::query()->with(['submitter:id,name']);
            $this->scopeDashboardQuery($query, $user);

            $search = trim((string) $request->query('q', ''));
            if ($search !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('submitted_by_name', 'like', $like)
                        ->orWhere('post_url', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            }

            if ($request->filled('from')) {
                $query->whereDate('posted_on', '>=', (string) $request->query('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('posted_on', '<=', (string) $request->query('to'));
            }

            $rows = $query
                ->orderByDesc('posted_on')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        } catch (\Throwable $e) {
            report($e);

            return $this->dashboardView($request, collect(), true, [
                'q' => $search ?? '',
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'view' => $viewMode,
            ]);
        }

        return $this->dashboardView($request, $rows, false, [
            'q' => $search,
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'view' => $viewMode,
        ]);
    }

    public function show(Request $request, SocialMediaPost $socialMediaPost): View
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canViewDashboard($user), 403);
        $this->assertCanAccessRecord($user, $socialMediaPost);

        $socialMediaPost->loadMissing(['submitter:id,name']);

        $preview = $this->previewForPost($socialMediaPost, $request);

        return view('social-media-posts.show', [
            'row' => $socialMediaPost,
            'currentRole' => (string) $user->role,
            'preview' => $preview,
            'dashboardRoute' => $user->role === 'state_admin'
                ? 'admin.social-media-posts.dashboard'
                : 'spoc.social-media-posts.dashboard',
            'destroyRoute' => $user->role === 'state_admin'
                ? 'admin.social-media-posts.destroy'
                : 'spoc.social-media-posts.destroy',
            'canDelete' => SocialMediaPostAccess::canDelete($user, $socialMediaPost),
        ]);
    }

    public function destroy(Request $request, SocialMediaPost $socialMediaPost): RedirectResponse
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canDelete($user, $socialMediaPost), 403);

        $dashboardRoute = $user->role === 'state_admin'
            ? 'admin.social-media-posts.dashboard'
            : 'spoc.social-media-posts.dashboard';

        $socialMediaPost->delete();

        return redirect()
            ->route($dashboardRoute)
            ->with('status', 'Social media post deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(SocialMediaPostAccess::canViewDashboard($user), 403);

        $query = SocialMediaPost::query()->with(['submitter:id,name']);
        $this->scopeDashboardQuery($query, $user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('submitted_by_name', 'like', $like)
                    ->orWhere('post_url', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('posted_on', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('posted_on', '<=', (string) $request->query('to'));
        }

        $rows = $query->orderByDesc('posted_on')->orderByDesc('id')->get();

        return $this->streamExportCsv($rows, 'social-media-posts-'.now()->format('Ymd_His').'.csv');
    }

    private function socialMediaPostsReady(): bool
    {
        if (! Schema::hasTable('social_media_posts')) {
            return false;
        }

        foreach (['posted_platforms', 'platform', 'thumbnail_url', 'preview_title'] as $column) {
            if (! Schema::hasColumn('social_media_posts', $column)) {
                return false;
            }
        }

        return true;
    }

    private function dashboardView(Request $request, mixed $rows, bool $migrationMissing, ?array $filters): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'state_admin';
        $viewMode = $filters['view'] ?? (string) $request->query('view', 'posts');
        if (! in_array($viewMode, ['list', 'posts'], true)) {
            $viewMode = 'posts';
        }

        return view('social-media-posts.dashboard', [
            'rows' => $rows,
            'migrationMissing' => $migrationMissing,
            'isPaginated' => ! $migrationMissing,
            'currentRole' => (string) $user->role,
            'isAdminView' => $isAdmin,
            'viewMode' => $viewMode,
            'filters' => array_merge(['q' => '', 'from' => '', 'to' => '', 'view' => $viewMode], $filters ?? []),
            'dashboardRoute' => $isAdmin ? 'admin.social-media-posts.dashboard' : 'spoc.social-media-posts.dashboard',
            'exportRoute' => $isAdmin ? 'admin.social-media-posts.export' : 'spoc.social-media-posts.export',
            'showRoute' => $isAdmin ? 'admin.social-media-posts.show' : 'spoc.social-media-posts.show',
            'createRoute' => SocialMediaPostAccess::canSubmit($user) ? 'spoc.social-media-posts.create' : null,
        ]);
    }

    private function scopeDashboardQuery($query, $user): void
    {
        if ($user->role === 'state_staff' && SocialMediaPostAccess::canSubmit($user)) {
            $query->where('submitted_by_user_id', (int) $user->id);
        }
    }

    private function assertCanAccessRecord($user, SocialMediaPost $row): void
    {
        if ($user->role === 'state_admin') {
            return;
        }

        if ($user->role === 'state_staff'
            && SocialMediaPostAccess::canSubmit($user)
            && (int) $row->submitted_by_user_id === (int) $user->id) {
            return;
        }

        abort(403);
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function withProxiedThumbnail(Request $request, array $resolved): array
    {
        $thumb = $resolved['thumbnail_url'] ?? null;
        if (! is_string($thumb) || $thumb === '' || ! $this->previewService()->shouldProxyThumbnail($thumb)) {
            return $resolved;
        }

        $routeName = $request->user()?->role === 'state_admin'
            ? 'admin.social-media-posts.thumbnail'
            : 'spoc.social-media-posts.thumbnail';

        $resolved['thumbnail_url'] = route($routeName, [
            'src' => rtrim(strtr(base64_encode($thumb), '+/', '-_'), '='),
        ]);

        return $resolved;
    }

    private function proxiedThumbnailUrl(Request $request, ?string $thumb): ?string
    {
        if (! is_string($thumb) || $thumb === '' || ! $this->previewService()->shouldProxyThumbnail($thumb)) {
            return $thumb;
        }

        $routeName = $request->user()?->role === 'state_admin'
            ? 'admin.social-media-posts.thumbnail'
            : 'spoc.social-media-posts.thumbnail';

        return route($routeName, [
            'src' => rtrim(strtr(base64_encode($thumb), '+/', '-_'), '='),
        ]);
    }

    /**
     * @return array{platform: string|null, thumbnail_url: string|null, preview_title: string|null}
     */
    private function previewMetaForUrl(string $url): array
    {
        $resolved = $this->previewService()->resolve($url);

        return [
            'platform' => ($resolved['platform'] ?? '') !== '' ? (string) $resolved['platform'] : null,
            'thumbnail_url' => SocialMediaPost::clampThumbnailUrl(
                is_string($resolved['thumbnail_url'] ?? null) && $resolved['thumbnail_url'] !== ''
                    ? (string) $resolved['thumbnail_url']
                    : null
            ),
            'preview_title' => SocialMediaPost::clampPreviewTitle(
                is_string($resolved['title'] ?? null) && $resolved['title'] !== ''
                    ? (string) $resolved['title']
                    : null
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewForPost(SocialMediaPost $post, ?Request $request = null): array
    {
        if (is_string($post->thumbnail_url) && $post->thumbnail_url !== '') {
            $thumb = $post->thumbnail_url;
            if ($request !== null) {
                $thumb = $this->proxiedThumbnailUrl($request, $thumb) ?? $thumb;
            }

            return [
                'mode' => 'thumbnail',
                'platform' => $post->platform ?: $this->previewService()->platformLabel((string) $post->post_url),
                'url' => (string) $post->post_url,
                'iframe_src' => null,
                'thumbnail_url' => $thumb,
                'title' => $post->preview_title,
                'author' => null,
                'message' => ($post->platform ?: 'This platform').' preview.',
            ];
        }

        $resolved = $this->previewService()->resolve((string) $post->post_url);

        return $request !== null ? $this->withProxiedThumbnail($request, $resolved) : $resolved;
    }

    private function previewService(): SocialMediaPostPreviewService
    {
        return app(SocialMediaPostPreviewService::class);
    }

    private function streamExportCsv(Collection $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Entry ID',
            'Posted on',
            'Post URL',
            'Posted on platforms',
            'Description',
            'Submitted by',
            'Created at',
            'Updated at',
        ];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                if (! $row instanceof SocialMediaPost) {
                    continue;
                }

                fputcsv($out, [
                    $row->id,
                    $row->posted_on?->format('Y-m-d') ?? '',
                    $row->post_url,
                    SocialMediaPostPlatforms::labelsText(is_array($row->posted_platforms) ? $row->posted_platforms : null),
                    $row->description ?? '',
                    $row->submitted_by_name,
                    $row->created_at?->format('Y-m-d H:i') ?? '',
                    $row->updated_at?->format('Y-m-d H:i') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
