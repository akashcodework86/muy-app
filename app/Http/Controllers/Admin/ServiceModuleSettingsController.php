<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\AdminAuditLogger;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * State admin-only pages for controlling the service module (maker-checker)
 * runtime behaviour. Changes are DB-backed via AppSettingsService, so they
 * take effect immediately without a redeploy.
 *
 * General page:
 *   - service_module.enabled       Master on/off for the workflow.
 *   - service_module.eligibility   Who staff can raise cases for.
 *
 * Per-service page:
 *   - services.accepts_new_service_cases   Pause new intake per service without
 *                                            turning the catalog row inactive.
 */
class ServiceModuleSettingsController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private AdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('admin.service-module-settings.edit', [
            'serviceModuleNavActive' => 'general',
            'enabled' => $this->settings->isEnabled('service_module.enabled'),
            'eligibility' => $this->settings->get('service_module.eligibility', 'onboarded_only'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'eligibility' => ['required', Rule::in(['all', 'onboarded_only'])],
        ]);

        $before = [
            'enabled' => $this->settings->isEnabled('service_module.enabled'),
            'eligibility' => $this->settings->get('service_module.eligibility', 'onboarded_only'),
        ];

        $after = [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'eligibility' => $validated['eligibility'],
        ];

        $this->settings->setMany([
            'service_module.enabled' => $after['enabled'],
            'service_module.eligibility' => $after['eligibility'],
        ], auth()->id());

        $this->auditLogger->record(
            $request,
            action: 'service_module.settings.updated',
            subjectType: 'app_settings',
            subjectId: null,
            before: $before,
            after: $after,
            description: 'State admin updated service module settings',
        );

        return redirect()
            ->route('admin.service-module-settings.edit')
            ->with('status', 'Service module settings updated.');
    }

    public function editServices(): View
    {
        return view('admin.service-module-settings.services', [
            'serviceModuleNavActive' => 'services',
            'serviceGroups' => $this->serviceGroupsForEdit(),
        ]);
    }

    public function updateServices(Request $request): RedirectResponse
    {
        $serviceCount = Service::query()->count();

        $validated = $request->validate([
            'accepts_new_service_cases' => array_merge(
                $serviceCount > 0 ? ['required'] : ['nullable'],
                ['array'],
            ),
            'accepts_new_service_cases.*' => ['boolean'],
        ]);

        $submitted = collect($validated['accepts_new_service_cases'] ?? [])
            ->mapWithKeys(fn (bool $v, string|int $k): array => [(int) $k => $v]);

        if ($serviceCount > 0) {
            $expectedIds = Service::query()->orderBy('id')->pluck('id')->values()->all();
            $receivedIds = $submitted->keys()->sort()->values()->all();
            if ($expectedIds !== $receivedIds) {
                return back()
                    ->withErrors([
                        'accepts_new_service_cases' => 'The service list changed. Refresh this page and try again.',
                    ])
                    ->withInput();
            }
        }

        $beforeToggles = Service::query()
            ->orderBy('id')
            ->pluck('accepts_new_service_cases', 'id')
            ->map(fn ($v): bool => (bool) $v)
            ->all();

        $toggleDiff = [];

        DB::transaction(function () use ($beforeToggles, $submitted, &$toggleDiff, $serviceCount): void {
            if ($serviceCount === 0) {
                return;
            }

            foreach ($beforeToggles as $id => $wasAccepting) {
                $id = (int) $id;
                $newVal = (bool) $submitted[$id];
                if ((bool) $wasAccepting !== $newVal) {
                    $toggleDiff[$id] = [
                        'before' => (bool) $wasAccepting,
                        'after' => $newVal,
                    ];
                }
                Service::query()->whereKey($id)->update([
                    'accepts_new_service_cases' => $newVal,
                ]);
            }
        });

        $auditPerServiceBefore = [];
        $auditPerServiceAfter = [];
        foreach ($toggleDiff as $id => $row) {
            $auditPerServiceBefore[$id] = $row['before'];
            $auditPerServiceAfter[$id] = $row['after'];
        }

        $this->auditLogger->record(
            $request,
            action: 'service_module.per_service.updated',
            subjectType: 'app_settings',
            subjectId: null,
            before: ['per_service_new_cases' => $auditPerServiceBefore === [] ? null : $auditPerServiceBefore],
            after: ['per_service_new_cases' => $auditPerServiceAfter === [] ? null : $auditPerServiceAfter],
            description: 'State admin updated per-service new-case availability',
        );

        return redirect()
            ->route('admin.service-module-settings.services')
            ->with('status', 'Per-service availability updated.');
    }

    /**
     * @return list<array{category: ?ServiceCategory, services: Collection<int, Service>}>
     */
    private function serviceGroupsForEdit(): array
    {
        $categories = ServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $services = Service::query()
            ->with('category')
            ->orderBy('service_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $placedIds = [];
        $groups = [];
        foreach ($categories as $category) {
            $subset = $services->where('service_category_id', $category->id)->values();
            foreach ($subset as $serviceRow) {
                $placedIds[(int) $serviceRow->id] = true;
            }
            if ($subset->isNotEmpty()) {
                $groups[] = ['category' => $category, 'services' => $subset];
            }
        }

        $uncat = $services->filter(fn (Service $s): bool => ! isset($placedIds[(int) $s->id]))->values();
        if ($uncat->isNotEmpty()) {
            $groups[] = ['category' => null, 'services' => $uncat];
        }

        return $groups;
    }
}
