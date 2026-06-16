<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentLibraryController extends Controller
{
    public function internalIndex(Request $request): View
    {
        $role = (string) ($request->user()->role ?? '');
        abort_if($role === '' || $role === Document::ROLE_INCUBATEE, 403);

        return $this->renderIndex($request, 'library.documents.index', $role, 'Internal document repository');
    }

    public function incubateeIndex(Request $request): View
    {
        $role = (string) ($request->user()->role ?? '');
        abort_if($role !== Document::ROLE_INCUBATEE, 403);

        return $this->renderIndex($request, 'incubatee.documents.index', $role, 'Document repository');
    }

    public function download(Request $request, Document $document)
    {
        $role = (string) ($request->user()->role ?? '');
        abort_unless($this->canView($document, $role), 403);

        $version = $document->latestVersion;
        abort_unless($version && $version->path !== '', 404);

        return Storage::disk((string) $version->disk)->download($version->path, $version->original_name);
    }

    private function renderIndex(Request $request, string $view, string $role, string $title): View
    {
        $q = trim((string) $request->query('q', ''));
        $catId = (int) $request->query('category', 0);
        $tag = trim((string) $request->query('tag', ''));

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
        if ($catId > 0) {
            $picked = DocumentCategory::query()->with('children:id,parent_id')->find($catId);
            if ($picked && $picked->parent_id === null) {
                $ids = array_merge([(int) $picked->id], $picked->children->pluck('id')->map(fn ($v) => (int) $v)->all());
                $query->whereIn('document_category_id', $ids);
            } else {
                $query->where('document_category_id', $catId);
            }
        }
        if ($tag !== '') {
            $query->where('tags', 'like', '%"'.$tag.'"%');
        }

        $docs = $query->paginate(20)->withQueryString();

        $allTags = [];
        foreach ($docs as $d) {
            foreach ($d->normalizedTags() as $t) {
                $allTags[$t] = true;
            }
        }
        $tags = array_keys($allTags);
        sort($tags, SORT_STRING);

        return view($view, [
            'titleText' => $title,
            'docs' => $docs,
            'categories' => DocumentCategory::query()->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'filters' => ['q' => $q, 'category' => $catId, 'tag' => $tag],
            'tags' => $tags,
        ]);
    }

    private function canView(Document $document, string $role): bool
    {
        return $role !== '' && $document->isVisibleToRole($role) && $document->latestVersion !== null;
    }
}
