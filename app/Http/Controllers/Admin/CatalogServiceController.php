<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCategory;
use App\Services\AdminAuditLogger;
use App\Support\ServiceFieldTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogServiceController extends Controller
{
    public function __construct(
        private AdminAuditLogger $auditLogger,
    ) {}

    public function create(Request $request): View
    {
        $categoryId = (int) $request->query('service_category_id', 0);
        $subcategories = ServiceCategory::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.service-catalog.services.create', [
            'subcategories' => $subcategories,
            'selectedCategoryId' => $categoryId,
            'deliverables' => Deliverable::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('deliverable_id') === '' || $request->input('deliverable_id') === null) {
            $request->merge(['deliverable_id' => null]);
        } else {
            $request->merge(['deliverable_id' => (int) $request->input('deliverable_id')]);
        }

        $validated = $request->validate([
            'service_category_id' => [
                'required',
                'integer',
                Rule::exists('service_categories', 'id')->where(fn ($q) => $q->whereNotNull('parent_id')),
            ],
            'code' => ['nullable', 'string', 'max:96', 'regex:/^[a-z0-9_]+$/', Rule::unique('services', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'allows_multiple' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'requires_document' => ['nullable', 'boolean'],
            'allowed_document_types' => ['nullable', 'array'],
            'allowed_document_types.*' => ['string', Rule::in(['pdf', 'image'])],
            'deliverable_id' => ['nullable', 'integer', 'exists:deliverables,id'],
            'reporting_tier' => ['required', 'string', Rule::in(['unset', 'key', 'non_key'])],
        ]);

        $fieldSchema = $this->validatedFieldSchema($request);
        $requiresDocument = $request->boolean('requires_document', false);
        $allowedDocTypes = $this->normalizeAllowedDocTypes($request->input('allowed_document_types'), $requiresDocument);

        $code = $validated['code'] ?? null;
        if ($code === null || $code === '') {
            $code = $this->uniqueCodeFromName($validated['name']);
        }

        $service = Service::query()->create([
            'service_category_id' => (int) $validated['service_category_id'],
            'deliverable_id' => isset($validated['deliverable_id']) ? (int) $validated['deliverable_id'] : null,
            'code' => $code,
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($request->boolean('is_active', true)),
            'allows_multiple' => (bool) $request->boolean('allows_multiple', false),
            'reporting_tier' => $validated['reporting_tier'],
            'requires_approval' => (bool) $request->boolean('requires_approval', false),
            'requires_document' => $requiresDocument,
            'allowed_document_types' => $allowedDocTypes,
            'field_schema' => $fieldSchema,
        ]);

        $this->auditLogger->record(
            $request,
            'catalog_service.created',
            Service::class,
            $service->id,
            null,
            ['code' => $service->code, 'name' => $service->name, 'service_category_id' => $service->service_category_id],
            'Catalog service created',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Service created.');
    }

    public function edit(Service $service): View
    {
        $service->loadMissing('category.parent');
        $subcategories = ServiceCategory::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.service-catalog.services.edit', [
            'service' => $service,
            'subcategories' => $subcategories,
            'deliverables' => Deliverable::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'fieldSchemaJson' => json_encode($service->field_schema ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        if ($request->input('deliverable_id') === '' || $request->input('deliverable_id') === null) {
            $request->merge(['deliverable_id' => null]);
        } else {
            $request->merge(['deliverable_id' => (int) $request->input('deliverable_id')]);
        }

        $validated = $request->validate([
            'service_category_id' => [
                'required',
                'integer',
                Rule::exists('service_categories', 'id')->where(fn ($q) => $q->whereNotNull('parent_id')),
            ],
            'code' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9_]+$/', Rule::unique('services', 'code')->ignore($service->id)],
            'name' => ['required', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'allows_multiple' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'requires_document' => ['nullable', 'boolean'],
            'allowed_document_types' => ['nullable', 'array'],
            'allowed_document_types.*' => ['string', Rule::in(['pdf', 'image'])],
            'deliverable_id' => ['nullable', 'integer', 'exists:deliverables,id'],
            'reporting_tier' => ['required', 'string', Rule::in(['unset', 'key', 'non_key'])],
        ]);

        $fieldSchema = $this->validatedFieldSchema($request);
        $requiresDocument = $request->boolean('requires_document', false);
        $allowedDocTypes = $this->normalizeAllowedDocTypes($request->input('allowed_document_types'), $requiresDocument);

        $before = [
            'code' => $service->code,
            'name' => $service->name,
            'service_category_id' => $service->service_category_id,
            'allows_multiple' => $service->allows_multiple,
            'reporting_tier' => $service->reporting_tier,
            'requires_approval' => $service->requires_approval,
            'requires_document' => $service->requires_document,
            'allowed_document_types' => $service->allowed_document_types,
            'is_active' => $service->is_active,
        ];

        $service->service_category_id = (int) $validated['service_category_id'];
        $service->deliverable_id = isset($validated['deliverable_id']) ? (int) $validated['deliverable_id'] : null;
        $service->code = $validated['code'];
        $service->name = $validated['name'];
        $service->sort_order = (int) ($validated['sort_order'] ?? 0);
        $service->is_active = (bool) $request->boolean('is_active', true);
        $service->allows_multiple = (bool) $request->boolean('allows_multiple', false);
        $service->reporting_tier = $validated['reporting_tier'];
        $service->requires_approval = (bool) $request->boolean('requires_approval', false);
        $service->requires_document = $requiresDocument;
        $service->allowed_document_types = $allowedDocTypes;
        $service->field_schema = $fieldSchema;
        $service->save();

        $this->auditLogger->record(
            $request,
            'catalog_service.updated',
            Service::class,
            $service->id,
            $before,
            [
                'code' => $service->code,
                'name' => $service->name,
                'service_category_id' => $service->service_category_id,
                'allows_multiple' => $service->allows_multiple,
                'reporting_tier' => $service->reporting_tier,
                'requires_approval' => $service->requires_approval,
                'requires_document' => $service->requires_document,
                'allowed_document_types' => $service->allowed_document_types,
                'is_active' => $service->is_active,
            ],
            'Catalog service updated',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Service updated.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        if (ServiceCase::query()->where('service_id', $service->id)->exists()) {
            return redirect()->route('admin.service-catalog.index')->withErrors([
                'delete' => 'This service has cases — deactivate it instead of deleting.',
            ]);
        }

        $id = $service->id;
        $before = ['code' => $service->code, 'name' => $service->name];
        $service->delete();

        $this->auditLogger->record(
            $request,
            'catalog_service.deleted',
            Service::class,
            $id,
            $before,
            null,
            'Catalog service deleted',
        );

        return redirect()->route('admin.service-catalog.index')->with('status', 'Service deleted.');
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function validatedFieldSchema(Request $request): ?array
    {
        $raw = $request->input('field_schema');
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages(['field_schema' => 'Invalid JSON for field schema.']);
            }
            $raw = $decoded;
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['field_schema' => 'Field schema must be a JSON array.']);
        }
        if ($raw === []) {
            return null;
        }

        foreach ($raw as $i => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['field_schema' => "Invalid row at index {$i}."]);
            }
            $key = $row['key'] ?? null;
            $label = $row['label'] ?? null;
            $type = (string) ($row['type'] ?? '');
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                throw ValidationException::withMessages([
                    'field_schema' => "Invalid key at index {$i}. Use snake_case (letters, numbers, underscores).",
                ]);
            }
            if (! is_string($label) || trim($label) === '') {
                throw ValidationException::withMessages(['field_schema' => "Missing label for key {$key}."]);
            }
            if (! ServiceFieldTypes::isValid($type)) {
                throw ValidationException::withMessages(['field_schema' => "Invalid type for {$key}."]);
            }
            if (ServiceFieldTypes::supportsOptions($type)) {
                $opts = $row['options'] ?? [];
                if (! is_array($opts) || $opts === []) {
                    throw ValidationException::withMessages(['field_schema' => "Field {$key} needs at least one option."]);
                }
            }
        }

        $normalized = ServiceFieldTypes::normalizeSchema($raw);
        if ($normalized === []) {
            throw ValidationException::withMessages(['field_schema' => 'No valid schema fields after normalisation.']);
        }

        return $normalized;
    }

    /**
     * Returns a whitelisted list of doc-type tags (pdf/image) or null.
     * Returns null when the service does not require documents, to keep the
     * column tidy.
     *
     * @param  mixed  $raw
     * @return list<string>|null
     */
    private function normalizeAllowedDocTypes($raw, bool $requiresDocument): ?array
    {
        if (! $requiresDocument) {
            return null;
        }
        if (! is_array($raw) || $raw === []) {
            return ['pdf', 'image'];
        }

        $clean = [];
        foreach ($raw as $v) {
            $v = is_string($v) ? strtolower($v) : null;
            if (in_array($v, ['pdf', 'image'], true)) {
                $clean[$v] = true;
            }
        }

        if ($clean === []) {
            return ['pdf', 'image'];
        }

        return array_keys($clean);
    }

    private function uniqueCodeFromName(string $name): string
    {
        $base = Str::snake(preg_replace('/[^a-zA-Z0-9]+/u', '_', $name) ?? '');
        $base = trim(preg_replace('/_+/', '_', $base) ?? '', '_');
        if ($base === '') {
            $base = 'service';
        }
        if (strlen($base) > 80) {
            $base = substr($base, 0, 80);
        }
        $code = $base;
        $i = 1;
        while (Service::query()->where('code', $code)->exists()) {
            $suffix = '_'.$i;
            $code = substr($base, 0, max(1, 96 - strlen($suffix))).$suffix;
            $i++;
        }

        return $code;
    }
}
