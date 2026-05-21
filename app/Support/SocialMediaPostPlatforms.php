<?php

namespace App\Support;

class SocialMediaPostPlatforms
{
    /**
     * @return array<string, string> slug => label
     */
    public static function options(): array
    {
        return config('social_media_posts.platforms', []);
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    public static function normalize(?array $slugs): array
    {
        if ($slugs === null || $slugs === []) {
            return [];
        }

        $allowed = array_keys(self::options());
        $normalized = [];
        foreach ($slugs as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || ! in_array($slug, $allowed, true)) {
                continue;
            }
            $normalized[$slug] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param  list<string>|null  $slugs
     */
    public static function labelsText(?array $slugs): string
    {
        $labels = self::labels($slugs);

        return $labels !== [] ? implode(', ', $labels) : '—';
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    public static function labels(?array $slugs): array
    {
        $options = self::options();
        $labels = [];
        foreach (self::normalize($slugs) as $slug) {
            $labels[] = $options[$slug] ?? ucfirst($slug);
        }

        return $labels;
    }

    public static function slugFromPreviewPlatform(?string $platform): ?string
    {
        $platform = strtolower(trim((string) $platform));
        if ($platform === '') {
            return null;
        }

        return match (true) {
            str_contains($platform, 'instagram') => 'instagram',
            str_contains($platform, 'youtube') => 'youtube',
            str_contains($platform, 'facebook') => 'facebook',
            str_contains($platform, 'linkedin') => 'linkedin',
            $platform === 'x' || str_contains($platform, 'twitter') => 'x',
            str_contains($platform, 'threads') => 'threads',
            default => null,
        };
    }
}
