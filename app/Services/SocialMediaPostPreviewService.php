<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialMediaPostPreviewService
{
    /** Hosts that block embedding in iframes — use thumbnail/card instead. */
    private const EMBED_BLOCKED_HOST_SUFFIXES = [
        'instagram.com',
        'facebook.com',
        'fb.com',
        'twitter.com',
        'x.com',
        'linkedin.com',
        'threads.net',
    ];

    /**
     * @return array{
     *     mode: 'empty'|'iframe'|'thumbnail'|'card',
     *     platform: string,
     *     url: string,
     *     iframe_src?: string|null,
     *     thumbnail_url?: string|null,
     *     title?: string|null,
     *     author?: string|null,
     *     message?: string|null,
     * }
     */
    public function resolve(string $rawUrl): array
    {
        $url = $this->normalizeUrl($rawUrl);
        if ($url === null) {
            return $this->emptyPreview();
        }

        $host = $this->hostKey($url);

        if ($youtubeEmbed = $this->youtubeEmbedSrc($url, $host)) {
            return [
                'mode' => 'iframe',
                'platform' => 'YouTube',
                'url' => $url,
                'iframe_src' => $youtubeEmbed,
                'thumbnail_url' => null,
                'title' => null,
                'author' => null,
                'message' => null,
            ];
        }

        $oembed = $this->fetchOembed($url, $host);
        if ($oembed !== null) {
            return $oembed;
        }

        $og = $this->fetchOpenGraph($url);
        if ($og !== null) {
            return $og;
        }

        return $this->cardPreview($url, $host, 'Preview unavailable — open the link to view this post.');
    }

    public function blocksIframeEmbed(string $rawUrl): bool
    {
        $url = $this->normalizeUrl($rawUrl);
        if ($url === null) {
            return true;
        }

        $host = $this->hostKey($url);

        if ($this->hostMatchesSuffixes($host, self::EMBED_BLOCKED_HOST_SUFFIXES)) {
            return true;
        }

        return $this->youtubeEmbedSrc($url, $host) === null
            && $this->hostMatchesSuffixes($host, ['youtube.com', 'youtu.be', 'm.youtube.com']);
    }

    public function platformLabel(string $rawUrl): string
    {
        $url = $this->normalizeUrl($rawUrl);
        if ($url === null) {
            return 'Link';
        }

        $host = $this->hostKey($url);

        return match (true) {
            str_contains($host, 'instagram') => 'Instagram',
            str_contains($host, 'facebook') || str_contains($host, 'fb.com') => 'Facebook',
            str_contains($host, 'youtube') || str_contains($host, 'youtu.be') => 'YouTube',
            str_contains($host, 'twitter') || $host === 'x.com' => 'X',
            str_contains($host, 'linkedin') => 'LinkedIn',
            str_contains($host, 'threads') => 'Threads',
            default => ucfirst(Str::before($host, '.')),
        };
    }

    private function normalizeUrl(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            $parsed = parse_url($raw);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parsed['host']);
        if ($this->isPrivateOrLocalHost($host)) {
            return null;
        }

        return $raw;
    }

    private function hostKey(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    private function isPrivateOrLocalHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    private function hostMatchesSuffixes(string $host, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private function youtubeEmbedSrc(string $url, string $host): ?string
    {
        if (! $this->hostMatchesSuffixes($host, ['youtube.com', 'youtu.be', 'm.youtube.com', 'www.youtube.com'])) {
            return null;
        }

        $id = null;
        if (str_contains($host, 'youtu.be')) {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $id = $path !== '' ? $path : null;
        } else {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = $query['v'] ?? null;
            if ($id === null) {
                $path = (string) parse_url($url, PHP_URL_PATH);
                if (preg_match('#/(?:embed|shorts|live)/([^/?]+)#', $path, $m)) {
                    $id = $m[1];
                }
            }
        }

        if (! is_string($id) || $id === '' || ! preg_match('/^[\w-]{6,}$/', $id)) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.rawurlencode($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOembed(string $url, string $host): ?array
    {
        $endpoints = [];

        if ($this->hostMatchesSuffixes($host, ['instagram.com', 'www.instagram.com'])) {
            $endpoints[] = 'https://www.instagram.com/api/v1/oembed/?url='.rawurlencode($url);
            $endpoints[] = 'https://api.instagram.com/oembed?url='.rawurlencode($url);
        }

        if ($this->hostMatchesSuffixes($host, ['twitter.com', 'x.com', 'www.twitter.com', 'mobile.twitter.com'])) {
            $endpoints[] = 'https://publish.twitter.com/oembed?url='.rawurlencode($url);
        }

        foreach ($endpoints as $endpoint) {
            $data = $this->httpJson($endpoint);
            if ($data === null) {
                continue;
            }

            $thumbnail = $data['thumbnail_url'] ?? null;
            $title = $data['title'] ?? ($data['author_name'] ?? null);
            $author = $data['author_name'] ?? null;
            $platform = $this->platformLabel($url);

            if (is_string($thumbnail) && $thumbnail !== '') {
                return [
                    'mode' => 'thumbnail',
                    'platform' => $platform,
                    'url' => $url,
                    'iframe_src' => null,
                    'thumbnail_url' => $thumbnail,
                    'title' => is_string($title) ? $title : null,
                    'author' => is_string($author) ? $author : null,
                    'message' => $platform.' does not allow live embed here. Thumbnail preview shown.',
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOpenGraph(string $url): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; MUY-MIS/1.0; +preview)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $html = (string) $response->body();
        if ($html === '') {
            return null;
        }

        $image = $this->metaContent($html, 'og:image') ?? $this->metaContent($html, 'twitter:image');
        $title = $this->metaContent($html, 'og:title') ?? $this->metaContent($html, 'twitter:title');
        $host = $this->hostKey($url);
        $platform = $this->platformLabel($url);

        if (is_string($image) && $image !== '') {
            $image = $this->absoluteUrl($image, $url);

            return [
                'mode' => 'thumbnail',
                'platform' => $platform,
                'url' => $url,
                'iframe_src' => null,
                'thumbnail_url' => $image,
                'title' => is_string($title) ? $title : null,
                'author' => null,
                'message' => $this->hostMatchesSuffixes($host, self::EMBED_BLOCKED_HOST_SUFFIXES)
                    ? $platform.' blocks embedding. Link preview shown.'
                    : 'Link preview.',
            ];
        }

        return null;
    }

    private function metaContent(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '/');
        $patterns = [
            '/<meta[^>]+property=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']'.$quoted.'["\']/i',
            '/<meta[^>]+name=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']'.$quoted.'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }

    private function absoluteUrl(string $maybeRelative, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $maybeRelative;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($maybeRelative, '//')) {
            return $parts['scheme'].':'.$maybeRelative;
        }

        if (str_starts_with($maybeRelative, '/')) {
            return $origin.$maybeRelative;
        }

        return $origin.'/'.ltrim($maybeRelative, '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function httpJson(string $url): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MUY-MIS/1.0; +preview)'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPreview(string $url, string $host, string $message): array
    {
        return [
            'mode' => 'card',
            'platform' => $this->platformLabel($url),
            'url' => $url,
            'iframe_src' => null,
            'thumbnail_url' => null,
            'title' => null,
            'author' => null,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPreview(): array
    {
        return [
            'mode' => 'empty',
            'platform' => '',
            'url' => '',
            'iframe_src' => null,
            'thumbnail_url' => null,
            'title' => null,
            'author' => null,
            'message' => 'Enter a valid URL to preview the post here.',
        ];
    }
}
