<?php

namespace App\Http\Controllers\Api\Incubatee;

use App\Http\Controllers\Controller;
use App\Support\UdmitaKoshCatalog;
use Illuminate\Http\JsonResponse;

class LearnController extends Controller
{
    public function show(): JsonResponse
    {
        $categories = array_map(static function (array $category): array {
            $videos = array_map(static function (array $video): array {
                $playlistId = (string) ($video['playlist_id'] ?? $video['youtube_id'] ?? '');
                $url = (string) ($video['url'] ?? '');

                return [
                    'title' => $video['title'] ?? '',
                    'title_en' => $video['title_en'] ?? '',
                    'channel' => $video['channel'] ?? '',
                    'youtube_id' => $playlistId,
                    'playlist_id' => $playlistId,
                    'duration' => $video['duration'] ?? '',
                    'kind' => $video['kind'] ?? 'playlist',
                    'url' => $url !== ''
                        ? $url
                        : ($playlistId !== '' ? 'https://www.youtube.com/playlist?list='.$playlistId : null),
                    'thumbnail' => null,
                ];
            }, $category['videos'] ?? []);

            return [
                'slug' => $category['slug'] ?? '',
                'title' => $category['title'] ?? '',
                'title_hi' => $category['hindi'] ?? ($category['title'] ?? ''),
                'emoji' => $category['emoji'] ?? '',
                'description' => $category['description'] ?? '',
                'description_hi' => $category['description_hi'] ?? ($category['description'] ?? ''),
                'video_count' => count($videos),
                'videos' => $videos,
            ];
        }, UdmitaKoshCatalog::categories());

        return response()->json([
            'categories' => array_values($categories),
            'resources' => UdmitaKoshCatalog::resourceDocuments(),
        ]);
    }
}
