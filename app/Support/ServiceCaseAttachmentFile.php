<?php

namespace App\Support;

use App\Models\ServiceCaseAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServiceCaseAttachmentFile
{
    public static function respond(ServiceCaseAttachment $attachment, bool $download = false): StreamedResponse
    {
        $disk = Storage::disk((string) $attachment->disk);
        $stream = null;
        if ($disk->exists($attachment->path)) {
            $stream = $disk->readStream($attachment->path);
        } else {
            $path = ltrim((string) $attachment->path, '/');
            $prefixedPrivate = str_starts_with($path, 'private/') ? $path : 'private/'.$path;
            $prefixedPublic = str_starts_with($path, 'public/') ? $path : 'public/'.$path;

            foreach (['local', 'public'] as $fallbackDiskName) {
                $fallbackDisk = Storage::disk($fallbackDiskName);
                foreach ([$path, $prefixedPrivate, $prefixedPublic] as $candidate) {
                    if ($fallbackDisk->exists($candidate)) {
                        $stream = $fallbackDisk->readStream($candidate);
                        break 2;
                    }
                }
            }
        }

        abort_unless(is_resource($stream), 404);

        $filename = (string) ($attachment->original_name ?: 'document');
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => (string) ($attachment->mime_type ?: 'application/octet-stream'),
        };
        $disposition = $download ? 'attachment' : 'inline';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }

    public static function isPreviewable(ServiceCaseAttachment $attachment): bool
    {
        $ext = strtolower((string) pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION));
        $mime = strtolower((string) ($attachment->mime_type ?? ''));

        return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            || str_starts_with($mime, 'image/')
            || $mime === 'application/pdf';
    }
}
