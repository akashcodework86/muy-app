<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class MediaGalleryThumbnailCache
{
    public const SIZE_THUMB = 480;

    public const SIZE_PREVIEW = 1600;

    public static function maxWidth(string $size): ?int
    {
        return match ($size) {
            'thumb' => self::SIZE_THUMB,
            'preview' => self::SIZE_PREVIEW,
            default => null,
        };
    }

    /**
     * @return array{path: string, mime: string}|null
     */
    public static function ensure(string $sourcePath, int $maxWidth): ?array
    {
        if ($maxWidth < 1 || ! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
        }

        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $mtime = (int) @filemtime($sourcePath);
        $hash = sha1($sourcePath.'|'.$mtime.'|'.$maxWidth);
        $relative = 'media-gallery-thumbs/'.$hash.'.jpg';
        $disk = Storage::disk('local');

        if ($disk->exists($relative)) {
            return [
                'path' => $disk->path($relative),
                'mime' => 'image/jpeg',
            ];
        }

        $jpeg = self::render($sourcePath, $maxWidth);
        if ($jpeg === null) {
            return null;
        }

        $disk->put($relative, $jpeg);

        return [
            'path' => $disk->path($relative),
            'mime' => 'image/jpeg',
        ];
    }

    private static function render(string $sourcePath, int $maxWidth): ?string
    {
        $src = self::load($sourcePath);
        if ($src === false) {
            return null;
        }

        $src = self::applyExifOrientation($sourcePath, $src);

        $width = imagesx($src);
        $height = imagesy($src);
        if ($width < 1 || $height < 1) {
            imagedestroy($src);

            return null;
        }

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = max(1, (int) round($height * ($maxWidth / $width)));
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            if ($dst === false) {
                imagedestroy($src);

                return null;
            }
            $white = imagecolorallocate($dst, 255, 255, 255);
            if ($white !== false) {
                imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $white);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        $ok = imagejpeg($src, null, $maxWidth <= self::SIZE_THUMB ? 78 : 82);
        imagedestroy($src);
        $data = ob_get_clean();

        if (! $ok || ! is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }

    /** @return \GdImage|false */
    private static function load(string $sourcePath): mixed
    {
        $ext = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($sourcePath),
            'png' => @imagecreatefrompng($sourcePath),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'gif' => @imagecreatefromgif($sourcePath),
            default => false,
        };
    }

    /** @param \GdImage $src */
    private static function applyExifOrientation(string $sourcePath, mixed $src): mixed
    {
        if (! function_exists('exif_read_data')) {
            return $src;
        }

        $ext = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg'], true)) {
            return $src;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $src;
        }

        imagedestroy($src);

        return $rotated;
    }
}
