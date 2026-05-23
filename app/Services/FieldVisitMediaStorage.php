<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldVisitMediaStorage
{
    public const MAX_PHOTOS_PER_REPORT = 15;

    private const DISK_FOLDER = 'field-visit-media';

    /**
     * @param  list<UploadedFile|null>  $files
     * @return list<array<string, mixed>>
     */
    public function storeMany(array $files): array
    {
        $mediaItems = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store(self::DISK_FOLDER);
            $mime = (string) ($file->getClientMimeType() ?? '');

            $mediaItems[] = [
                'path' => $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) ($file->getSize() ?? 0),
                'type' => Str::startsWith($mime, 'image/') ? 'image' : 'file',
            ];
        }

        return $mediaItems;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemAt(Model $report, int $index): ?array
    {
        $items = collect((array) $report->visit_media_json)
            ->filter(fn ($item) => is_array($item) && (string) ($item['path'] ?? '') !== '')
            ->values();

        $item = $items->get($index);

        return is_array($item) ? $item : null;
    }

    public function download(
        Model $report,
        int $index,
        bool $inline = false,
    ): StreamedResponse {
        $item = $this->itemAt($report, $index);
        abort_if($item === null, 404);

        $path = (string) ($item['path'] ?? '');
        abort_unless($path !== '' && Storage::exists($path), 404);

        $name = (string) ($item['original_name'] ?? basename($path));
        $mime = (string) ($item['mime'] ?? '');

        if ($inline) {
            return Storage::response($path, $name, [
                'Content-Type' => $mime !== '' ? $mime : Storage::mimeType($path),
                'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            ]);
        }

        return Storage::download($path, $name);
    }

    public function legacyDownload(Model $report): StreamedResponse
    {
        abort_if(! $report->attachment_path, 404);
        abort_unless(Storage::exists($report->attachment_path), 404);

        return Storage::download(
            $report->attachment_path,
            $report->attachment_original_name ?: basename($report->attachment_path)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $newItems
     * @return list<array<string, mixed>>
     */
    public function mergeOntoReport(Model $report, array $newItems): array
    {
        $merged = array_merge($report->visitMediaItems(), $newItems);

        return array_values(array_slice($merged, 0, self::MAX_PHOTOS_PER_REPORT));
    }

    public function removeAt(Model $report, int $index): array
    {
        $items = $report->visitMediaItems();
        abort_if($index < 0 || $index >= count($items), 404);

        $removed = $items[$index];
        $path = (string) ($removed['path'] ?? '');
        if ($path !== '' && Storage::exists($path)) {
            Storage::delete($path);
        }

        unset($items[$index]);

        return array_values($items);
    }

    public function deleteAllForReport(Model $report): void
    {
        foreach ($report->visitMediaItems() as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && Storage::exists($path)) {
                Storage::delete($path);
            }
        }

        $legacyPath = (string) ($report->attachment_path ?? '');
        if ($legacyPath !== '' && Storage::exists($legacyPath)) {
            Storage::delete($legacyPath);
        }
    }
}
