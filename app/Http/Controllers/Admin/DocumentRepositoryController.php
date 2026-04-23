<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentRepositoryController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = (int) $request->query('category', 0);
        $role = trim((string) $request->query('role', ''));

        $query = Document::query()
            ->with(['category', 'latestVersion', 'updater'])
            ->orderByDesc('updated_at');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('tags', 'like', $like);
            });
        }
        if ($category > 0) {
            $picked = DocumentCategory::query()->with('children:id,parent_id')->find($category);
            if ($picked && $picked->parent_id === null) {
                $ids = array_merge([(int) $picked->id], $picked->children->pluck('id')->map(fn ($v) => (int) $v)->all());
                $query->whereIn('document_category_id', $ids);
            } else {
                $query->where('document_category_id', $category);
            }
        }
        if ($role !== '' && in_array($role, Document::ALLOWED_ROLES, true)) {
            $query->where('allowed_roles', 'like', '%"'.$role.'"%');
        }

        $categories = DocumentCategory::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return view('admin.documents.index', [
            'docs' => $query->paginate(20)->withQueryString(),
            'categories' => $categories,
            'filters' => ['q' => $q, 'category' => $category, 'role' => $role],
            'roles' => Document::ALLOWED_ROLES,
        ]);
    }

    public function create(): View
    {
        [$rootCategories, $subcategoriesByRoot] = $this->categoryPickers();

        return view('admin.documents.create', [
            'rootCategories' => $rootCategories,
            'subcategoriesByRoot' => $subcategoriesByRoot,
            'roles' => Document::ALLOWED_ROLES,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
        ]);

        $name = trim((string) $validated['name']);
        $existing = DocumentCategory::query()
            ->whereNull('parent_id')
            ->where('name', $name)
            ->first();
        if ($existing) {
            return redirect()->route('admin.documents.index')->with('status', 'Category already exists.');
        }

        $cat = $this->firstOrCreateCategory($name, null);

        $this->auditLogger->record(
            $request,
            'document.category_created',
            DocumentCategory::class,
            $cat->id,
            null,
            ['name' => $cat->name, 'slug' => $cat->slug, 'parent_id' => null],
            'Document category created'
        );

        return redirect()->route('admin.documents.index')->with('status', 'Category created.');
    }

    public function storeSubcategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['required', 'integer', Rule::exists('document_categories', 'id')],
            'name' => ['required', 'string', 'max:160'],
        ]);

        $parent = DocumentCategory::query()
            ->whereKey((int) $validated['parent_id'])
            ->whereNull('parent_id')
            ->firstOrFail();

        $name = trim((string) $validated['name']);
        $existing = DocumentCategory::query()
            ->where('parent_id', (int) $parent->id)
            ->where('name', $name)
            ->first();
        if ($existing) {
            return redirect()->route('admin.documents.index')->with('status', 'Subcategory already exists under selected category.');
        }

        $sub = $this->firstOrCreateCategory($name, (int) $parent->id);

        $this->auditLogger->record(
            $request,
            'document.subcategory_created',
            DocumentCategory::class,
            $sub->id,
            null,
            ['name' => $sub->name, 'slug' => $sub->slug, 'parent_id' => $parent->id],
            'Document subcategory created'
        );

        return redirect()->route('admin.documents.index')->with('status', 'Subcategory created.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDocumentRequest($request, requireFile: true);
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($validated, $request, $userId): void {
            $categoryId = $this->resolveCategoryId(
                $validated['category_name'] ?? null,
                $validated['subcategory_name'] ?? null,
                (int) ($validated['document_category_id'] ?? 0),
                (int) ($validated['document_subcategory_id'] ?? 0)
            );
            $doc = Document::query()->create([
                'document_category_id' => $categoryId,
                'title' => trim((string) $validated['title']),
                'tags' => $this->normalizeTags($validated['tags'] ?? ''),
                'allowed_roles' => $this->normalizeAllowedRoles($validated['allowed_roles'] ?? []),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $version = $this->storeVersion($doc, $request->file('file'), $userId);
            $doc->update(['latest_version_id' => $version->id]);

            $this->auditLogger->record(
                $request,
                'document.created',
                Document::class,
                $doc->id,
                null,
                [
                    'title' => $doc->title,
                    'category_id' => $doc->document_category_id,
                    'allowed_roles' => $doc->allowed_roles,
                    'version' => $version->version_no,
                ],
                'Document created'
            );
        });

        return redirect()->route('admin.documents.index')->with('status', 'Document uploaded.');
    }

    public function edit(Document $document): View
    {
        $document->load(['category', 'versions.uploader', 'latestVersion']);
        [$rootCategories, $subcategoriesByRoot] = $this->categoryPickers();

        return view('admin.documents.edit', [
            'doc' => $document,
            'rootCategories' => $rootCategories,
            'subcategoriesByRoot' => $subcategoriesByRoot,
            'roles' => Document::ALLOWED_ROLES,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $validated = $this->validateDocumentRequest($request, requireFile: false);

        $before = [
            'title' => $document->title,
            'category_id' => $document->document_category_id,
            'tags' => $document->tags,
            'allowed_roles' => $document->allowed_roles,
        ];

        $document->update([
            'document_category_id' => $this->resolveCategoryId(
                $validated['category_name'] ?? null,
                $validated['subcategory_name'] ?? null,
                (int) ($validated['document_category_id'] ?? 0),
                (int) ($validated['document_subcategory_id'] ?? 0)
            ),
            'title' => trim((string) $validated['title']),
            'tags' => $this->normalizeTags($validated['tags'] ?? ''),
            'allowed_roles' => $this->normalizeAllowedRoles($validated['allowed_roles'] ?? []),
            'updated_by' => (int) $request->user()->id,
        ]);

        $this->auditLogger->record(
            $request,
            'document.updated',
            Document::class,
            $document->id,
            $before,
            [
                'title' => $document->title,
                'category_id' => $document->document_category_id,
                'tags' => $document->tags,
                'allowed_roles' => $document->allowed_roles,
            ],
            'Document metadata updated'
        );

        return redirect()->route('admin.documents.edit', $document)->with('status', 'Document details updated.');
    }

    public function uploadVersion(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:pdf,docx,xlsx,pptx,jpg,jpeg,png'],
        ]);

        $version = $this->storeVersion($document, $request->file('file'), (int) $request->user()->id);
        $document->update([
            'latest_version_id' => $version->id,
            'updated_by' => (int) $request->user()->id,
        ]);

        $this->auditLogger->record(
            $request,
            'document.version_uploaded',
            Document::class,
            $document->id,
            null,
            ['version' => $version->version_no, 'original_name' => $version->original_name],
            'Document new version uploaded'
        );

        return redirect()->route('admin.documents.edit', $document)->with('status', 'New version uploaded (v'.$version->version_no.').');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        foreach ($document->versions as $v) {
            $v->deleteFileIfLocal();
        }

        $before = [
            'title' => $document->title,
            'category_id' => $document->document_category_id,
            'allowed_roles' => $document->allowed_roles,
        ];
        $id = $document->id;
        $document->delete();

        $this->auditLogger->record(
            $request,
            'document.deleted',
            Document::class,
            $id,
            $before,
            null,
            'Document deleted'
        );

        return redirect()->route('admin.documents.index')->with('status', 'Document deleted.');
    }

    private function validateDocumentRequest(Request $request, bool $requireFile): array
    {
        $fileRules = ['nullable', 'file', 'max:25600', 'mimes:pdf,docx,xlsx,pptx,jpg,jpeg,png'];
        if ($requireFile) {
            $fileRules[0] = 'required';
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'document_category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'document_subcategory_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'category_name' => ['nullable', 'string', 'max:160'],
            'subcategory_name' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'string', 'max:1200'],
            'allowed_roles' => ['required', 'array', 'min:1'],
            'allowed_roles.*' => ['string', Rule::in(Document::ALLOWED_ROLES)],
            'file' => $fileRules,
        ]);
    }

    private function resolveCategoryId(
        ?string $newCategoryName,
        ?string $newSubcategoryName,
        int $existingRootId,
        int $existingSubcategoryId
    ): ?int
    {
        $rootName = trim((string) $newCategoryName);
        $subName = trim((string) $newSubcategoryName);

        $root = null;
        if ($rootName !== '') {
            $root = $this->firstOrCreateCategory($rootName, null);
        } elseif ($existingRootId > 0) {
            $root = DocumentCategory::query()->whereKey($existingRootId)->whereNull('parent_id')->first();
        }

        if ($subName !== '') {
            if (! $root) {
                $root = $this->firstOrCreateCategory('General', null);
            }

            return $this->firstOrCreateCategory($subName, (int) $root->id)->id;
        }

        if ($existingSubcategoryId > 0) {
            $sub = DocumentCategory::query()->whereKey($existingSubcategoryId)->whereNotNull('parent_id')->first();
            if ($sub) {
                return (int) $sub->id;
            }
        }

        return $root ? (int) $root->id : null;
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(string $raw): array
    {
        $parts = preg_split('/[,|]/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $v = trim((string) $p);
            if ($v !== '') {
                $out[$v] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeAllowedRoles(mixed $raw): array
    {
        $roles = is_array($raw) ? $raw : [];
        $roles = array_values(array_intersect(Document::ALLOWED_ROLES, array_map('strval', $roles)));
        if ($roles === []) {
            return [Document::ROLE_STATE_ADMIN];
        }

        return $roles;
    }

    private function storeVersion(Document $document, \Illuminate\Http\UploadedFile $file, int $userId): DocumentVersion
    {
        $nextNo = ((int) $document->versions()->max('version_no')) + 1;
        $dir = 'documents/'.$document->id;
        $stored = Storage::disk('local')->putFile($dir, $file);
        if (! is_string($stored) || $stored === '') {
            throw new \RuntimeException('Could not store document file.');
        }

        return $document->versions()->create([
            'version_no' => $nextNo,
            'disk' => 'local',
            'path' => $stored,
            'original_name' => (string) ($file->getClientOriginalName() ?: 'file'),
            'mime_type' => (string) ($file->getClientMimeType() ?: ''),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, DocumentCategory>, 1: array<int, array<int, array{id:int,name:string}>>}
     */
    private function categoryPickers(): array
    {
        $roots = DocumentCategory::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        $subs = [];
        foreach ($roots as $root) {
            $subs[(int) $root->id] = $root->children
                ->map(fn (DocumentCategory $c) => ['id' => (int) $c->id, 'name' => $c->name])
                ->values()
                ->all();
        }

        return [$roots, $subs];
    }

    private function firstOrCreateCategory(string $name, ?int $parentId): DocumentCategory
    {
        $name = trim($name);
        $existing = DocumentCategory::query()
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'category';
        }
        $base = $slug;
        $i = 2;
        while (DocumentCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return DocumentCategory::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
        ]);
    }
}
