<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $roots = ServiceCategory::query()
            ->whereNull('parent_id')
            ->with(['children.services', 'services'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.service-catalog.index', ['roots' => $roots]);
    }

    public function create(Request $request): View
    {
        $parentId = $request->query('parent_id');
        $parent = null;
        if ($parentId !== null && $parentId !== '') {
            $parent = ServiceCategory::query()->whereKey((int) $parentId)->whereNull('parent_id')->firstOrFail();
        }

        return view('admin.service-catalog.categories.create', ['parent' => $parent]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        if ($parentId) {
            $parent = ServiceCategory::query()->whereKey($parentId)->firstOrFail();
            if ($parent->parent_id !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'parent_id' => 'Only one subcategory level is allowed (parent must be a top-level category).',
                ]);
            }
        }

        $slug = $this->uniqueSlug($validated['name']);

        $category = ServiceCategory::query()->create([
            'parent_id' => $parentId,
            'slug' => $slug,
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->auditLogger->record(
            $request,
            'service_category.created',
            ServiceCategory::class,
            $category->id,
            null,
            ['slug' => $category->slug, 'name' => $category->name, 'parent_id' => $category->parent_id],
            'Service category created',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Category saved.');
    }

    public function edit(ServiceCategory $serviceCategory): View
    {
        $serviceCategory->loadMissing('parent');

        return view('admin.service-catalog.categories.edit', ['category' => $serviceCategory]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($request->input('parent_id') === '' || $request->input('parent_id') === null) {
            $request->merge(['parent_id' => null]);
        } else {
            $request->merge(['parent_id' => (int) $request->input('parent_id')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        $before = ['name' => $serviceCategory->name, 'sort_order' => $serviceCategory->sort_order, 'parent_id' => $serviceCategory->parent_id];

        $parentId = $validated['parent_id'] ?? null;
        $parentId = $parentId !== null ? (int) $parentId : null;

        if ($parentId === $serviceCategory->id) {
            return redirect()->back()->withInput()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        if ($parentId) {
            $parent = ServiceCategory::query()->whereKey($parentId)->firstOrFail();
            if ($parent->parent_id !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'parent_id' => 'Only one subcategory level is allowed (parent must be a top-level category).',
                ]);
            }
            if ($this->categoryIsOrDescendsFrom($parentId, $serviceCategory->id)) {
                return redirect()->back()->withInput()->withErrors(['parent_id' => 'Invalid parent choice.']);
            }
        }

        $serviceCategory->name = $validated['name'];
        $serviceCategory->sort_order = (int) ($validated['sort_order'] ?? 0);
        $serviceCategory->parent_id = $parentId;
        $serviceCategory->save();

        $this->auditLogger->record(
            $request,
            'service_category.updated',
            ServiceCategory::class,
            $serviceCategory->id,
            $before,
            ['name' => $serviceCategory->name, 'sort_order' => $serviceCategory->sort_order, 'parent_id' => $serviceCategory->parent_id],
            'Service category updated',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($serviceCategory->children()->exists()) {
            return redirect()->route('admin.service-catalog.index')->withErrors([
                'delete' => 'Remove child categories first.',
            ]);
        }

        if ($serviceCategory->services()->exists()) {
            return redirect()->route('admin.service-catalog.index')->withErrors([
                'delete' => 'Remove or move services under this category first.',
            ]);
        }

        $id = $serviceCategory->id;
        $before = ['slug' => $serviceCategory->slug, 'name' => $serviceCategory->name];
        $serviceCategory->delete();

        $this->auditLogger->record(
            $request,
            'service_category.deleted',
            ServiceCategory::class,
            $id,
            $before,
            null,
            'Service category deleted',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Category deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }
        $slug = $base;
        $i = 1;
        while (ServiceCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function categoryIsOrDescendsFrom(int $categoryId, int $ancestorId): bool
    {
        if ($categoryId === $ancestorId) {
            return true;
        }
        $current = ServiceCategory::query()->whereKey($categoryId)->first();
        while ($current && $current->parent_id) {
            if ((int) $current->parent_id === $ancestorId) {
                return true;
            }
            $current = ServiceCategory::query()->whereKey((int) $current->parent_id)->first();
        }

        return false;
    }
}
