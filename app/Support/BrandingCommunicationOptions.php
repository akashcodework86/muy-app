<?php

namespace App\Support;

final class BrandingCommunicationOptions
{
    /**
     * @return array<string, string>
     */
    public static function storyTypes(): array
    {
        return config('branding_communication.story_types', []);
    }

    public static function storyTypeLabel(string $value): string
    {
        return self::storyTypes()[$value] ?? $value;
    }

    /**
     * @return array<string, string>
     */
    public static function distributionModes(): array
    {
        return config('branding_communication.distribution_modes', []);
    }

    public static function distributionModeLabel(string $value): string
    {
        return self::distributionModes()[$value] ?? $value;
    }

    /**
     * @return array<string, string>
     */
    public static function mediaTypes(): array
    {
        return config('branding_communication.media_types', []);
    }

    public static function mediaTypeLabel(string $value): string
    {
        return self::mediaTypes()[$value] ?? $value;
    }
}
