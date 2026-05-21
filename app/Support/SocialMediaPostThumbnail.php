<?php

namespace App\Support;

use App\Services\SocialMediaPostPreviewService;
use Illuminate\Support\Facades\Route;

final class SocialMediaPostThumbnail
{
    public static function displayUrl(?string $thumbnailUrl, ?string $userRole = null): ?string
    {
        if (! is_string($thumbnailUrl) || $thumbnailUrl === '') {
            return null;
        }

        $service = app(SocialMediaPostPreviewService::class);
        if (! $service->shouldProxyThumbnail($thumbnailUrl)) {
            return $thumbnailUrl;
        }

        $role = $userRole ?? (string) (auth()->user()?->role ?? '');
        $routeName = $role === 'state_admin'
            ? 'admin.social-media-posts.thumbnail'
            : 'spoc.social-media-posts.thumbnail';

        if (! Route::has($routeName)) {
            return $thumbnailUrl;
        }

        return route($routeName, [
            'src' => rtrim(strtr(base64_encode($thumbnailUrl), '+/', '-_'), '='),
        ]);
    }
}
