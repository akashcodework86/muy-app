<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Services\AdminAuditLogger;
use App\Services\ServiceTargetDeliverableSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
        private ServiceTargetDeliverableSyncService $serviceDeliverables,
    ) {}

    public function index(): View
    {
        $categories = ServiceCategory::query()
            ->with('services')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.service-catalog.index', ['categories' => $categories]);
    }

    public function create(Request $request): View
    {
        return view('admin.service-catalog.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'target_mode' => ['required', 'string', 'in:service,category'],
        ]);

        $slug = $this->uniqueSlug($validated['name']);

        $category = ServiceCategory::query()->create([
            'parent_id' => null,
            'slug' => $slug,
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'target_mode' => (string) ($validated['target_mode'] ?? ServiceCategory::TARGET_MODE_SERVICE),
        ]);

        $this->auditLogger->record(
            $request,
            'service_category.created',
            ServiceCategory::class,
            $category->id,
            null,
            ['slug' => $category->slug, 'name' => $category->name, 'parent_id' => $category->parent_id, 'target_mode' => $category->target_mode],
            'Service category created',
        );

        $this->serviceDeliverables->syncAllServices();

        return redirect()->route('admin.service-catalog.index')->with('status', 'Category saved.');
    }

    public function edit(ServiceCategory $serviceCategory): View
    {
        return view('admin.service-catalog.categories.edit', ['category' => $serviceCategory]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'target_mode' => ['required', 'string', 'in:service,category'],
        ]);

        $before = ['name' => $serviceCategory->name, 'sort_order' => $serviceCategory->sort_order, 'parent_id' => $serviceCategory->parent_id, 'target_mode' => $serviceCategory->target_mode];

        $serviceCategory->name = $validated['name'];
        $serviceCategory->sort_order = (int) ($validated['sort_order'] ?? 0);
        $serviceCategory->target_mode = (string) ($validated['target_mode'] ?? ServiceCategory::TARGET_MODE_SERVICE);
        $serviceCategory->parent_id = null;
        $serviceCategory->save();

        $this->auditLogger->record(
            $request,
            'service_category.updated',
            ServiceCategory::class,
            $serviceCategory->id,
            $before,
            ['name' => $serviceCategory->name, 'sort_order' => $serviceCategory->sort_order, 'parent_id' => $serviceCategory->parent_id, 'target_mode' => $serviceCategory->target_mode],
            'Service category updated',
        );

        $this->serviceDeliverables->syncAllServices();

        return redirect()->route('admin.service-catalog.index')->with('status', 'Category updated.');
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
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
}
