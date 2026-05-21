<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaPost extends Model
{
    public const PREVIEW_TITLE_MAX_LENGTH = 500;

    public const THUMBNAIL_URL_MAX_LENGTH = 2048;

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'posted_on',
        'post_url',
        'posted_platforms',
        'platform',
        'thumbnail_url',
        'preview_title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'posted_platforms' => 'array',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public static function clampPreviewTitle(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $title);
        $firstLine = trim(explode("\n", $normalized, 2)[0]);

        return self::clampString($firstLine !== '' ? $firstLine : trim($title), self::PREVIEW_TITLE_MAX_LENGTH);
    }

    public static function clampThumbnailUrl(?string $url): ?string
    {
        return self::clampString($url, self::THUMBNAIL_URL_MAX_LENGTH);
    }

    private static function clampString(?string $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
