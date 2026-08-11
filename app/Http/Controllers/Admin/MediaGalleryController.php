<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Hub;
use App\Services\MediaGalleryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class MediaGalleryController extends Controller
{
    public function __construct(
        private readonly MediaGalleryService $gallery,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        [$hubs, $districts] = $this->filterOptions($filters['hub']);

        return view('admin.media-gallery.index', [
            'sections' => $this->gallery->sectionSummaries($filters),
            'filters' => $filters,
            'hubs' => $hubs,
            'districts' => $districts,
        ]);
    }

    public function section(Request $request, string $section): View
    {
        $meta = $this->gallery->section($section);
        abort_unless($meta !== null, 404);

        $filters = $this->filtersFromRequest($request);
        [$hubs, $districts] = $this->filterOptions($filters['hub']);
        $albums = $this->gallery->paginateAlbums($section, $filters);

        return view('admin.media-gallery.section', [
            'sectionKey' => $section,
            'section' => $meta,
            'albums' => $albums,
            'filters' => $filters,
            'hubs' => $hubs,
            'districts' => $districts,
        ]);
    }

    public function show(string $section, int $record): View
    {
        $data = $this->gallery->album($section, $record);
        abort_unless($data !== null, 404);

        return view('admin.media-gallery.show', [
            'sectionKey' => $section,
            'section' => $data['section'],
            'album' => $data['album'],
            'photos' => $data['photos'],
        ]);
    }

    public function photo(
        Request $request,
        string $section,
        int $record,
        string $collection,
    ): StreamedResponse|BinaryFileResponse {
        $index = max(0, (int) $request->query('index', 0));
        $item = $this->gallery->resolveMediaItem($section, $record, $collection, $index);
        abort_unless($item !== null, 404);

        $disk = Storage::disk($item['disk']);
        $path = $item['path'];
        $filename = $item['original_name'];
        $mime = $item['mime'] !== '' ? $item['mime'] : (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        $inline = $request->boolean('inline') && (
            str_starts_with(strtolower($mime), 'image/')
            || preg_match('/\.(jpe?g|png|webp|gif)$/i', $filename) === 1
        );

        if ($inline) {
            return response()->file($disk->path($path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            ]);
        }

        return $disk->download($path, $filename);
    }

    public function downloadZip(string $section, int $record): StreamedResponse|Response
    {
        $data = $this->gallery->album($section, $record);
        abort_unless($data !== null, 404);

        $files = $this->gallery->resolveAlbumMediaFiles($section, $record);
        abort_if($files === [], 404);

        $zipName = $this->safeFilename($section.'-'.$record.'-photos').'.zip';
        $tmp = tempnam(sys_get_temp_dir(), 'mgzip_');
        abort_unless(is_string($tmp), 500);

        $zip = new ZipArchive;
        abort_unless($zip->open($tmp, ZipArchive::OVERWRITE) === true, 500);

        $usedNames = [];
        foreach ($files as $i => $file) {
            $base = $this->safeFilename((string) $file['original_name']);
            if ($base === '') {
                $base = 'photo-'.($i + 1).'.jpg';
            }
            $name = $base;
            $n = 1;
            while (isset($usedNames[strtolower($name)])) {
                $name = pathinfo($base, PATHINFO_FILENAME).'-'.$n;
                $ext = pathinfo($base, PATHINFO_EXTENSION);
                if ($ext !== '') {
                    $name .= '.'.$ext;
                }
                $n++;
            }
            $usedNames[strtolower($name)] = true;
            $zip->addFile(Storage::disk($file['disk'])->path($file['path']), $name);
        }
        $zip->close();

        return response()->streamDownload(function () use ($tmp): void {
            $handle = fopen($tmp, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
            @unlink($tmp);
        }, $zipName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return array{hub: int|null, district: int|null, from: string, to: string, q: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'hub' => $request->integer('hub') ?: null,
            'district' => $request->integer('district') ?: null,
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function filterOptions(?int $hubId): array
    {
        $hubs = Hub::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $districts = District::query()
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->orderBy('name')
            ->get(['id', 'name', 'hub_id']);

        return [$hubs, $districts];
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w.\-]+/u', '-', $name) ?? 'file';
        $name = trim($name, '.-');

        return $name !== '' ? $name : 'file';
    }
}
