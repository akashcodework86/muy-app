<?php

namespace App\Http\Controllers\Api\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $role = Document::ROLE_INCUBATEE;

        $query = Document::query()
            ->where('allowed_roles', 'like', '%"'.$role.'"%')
            ->whereNotNull('latest_version_id')
            ->with(['category', 'latestVersion'])
            ->orderByDesc('updated_at');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('tags', 'like', $like);
            });
        }

        $docs = $query->limit(100)->get();

        return response()->json([
            'documents' => $docs->map(function (Document $document) {
                $version = $document->latestVersion;

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category?->name,
                    'tags' => $document->normalizedTags(),
                    'original_name' => $version?->original_name,
                    'mime_type' => $version?->mime_type,
                    'size_bytes' => $version?->size_bytes ?? 0,
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $role = (string) ($request->user()?->role ?? '');
        abort_unless($role === Document::ROLE_INCUBATEE && $document->isVisibleToRole($role), 403);

        $version = $document->latestVersion;
        abort_unless($version && $version->path !== '', 404);

        return Storage::disk((string) $version->disk)->download($version->path, $version->original_name);
    }
}
