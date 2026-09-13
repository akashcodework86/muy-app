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
                $id = (string) ($video['youtube_id'] ?? '');

                return [
                    'title' => $video['title'] ?? '',
                    'channel' => $video['channel'] ?? '',
                    'youtube_id' => $id,
                    'duration' => $video['duration'] ?? '',
                    'url' => $id !== '' ? 'https://www.youtube.com/watch?v='.$id : null,
                    'thumbnail' => $id !== '' ? 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg' : null,
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
