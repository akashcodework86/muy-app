<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditLogger;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * State admin-only page for controlling the service module (maker-checker)
 * runtime behaviour. Changes are DB-backed via AppSettingsService, so they
 * take effect immediately without a redeploy.
 *
 * Knobs:
 *   - service_module.enabled              Master on/off for the workflow.
 *                                         When OFF: staff cannot CREATE new cases;
 *                                         existing draft / pending / sent_back cases
 *                                         remain editable so they can be closed out.
 *   - service_module.eligibility          Who staff can raise cases for:
 *                                           'all'             = any CFA submission in their district
 *                                           'onboarded_only'  = only CFAs linked to at least one
 *                                                               onboarding batch
 *   - service_module.staff_delete_enabled Show Delete in district staff Services actions
 *                                         (list + detail) and allow destroy routes.
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
            'enabled' => $this->settings->isEnabled('service_module.enabled'),
            'eligibility' => $this->settings->get('service_module.eligibility', 'onboarded_only'),
            'staffDeleteEnabled' => $this->settings->isEnabled('service_module.staff_delete_enabled'),
            'deliverablesIndicatorMetadataEditable' => $this->settings->isEnabled('deliverables.indicator_metadata_editable'),
            'targetsAllocationEditable' => $this->settings->isEnabled('targets.allocation_editable'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'eligibility' => ['required', Rule::in(['all', 'onboarded_only'])],
            'staff_delete_enabled' => ['nullable', 'boolean'],
            'deliverables_indicator_metadata_editable' => ['nullable', 'boolean'],
            'targets_allocation_editable' => ['nullable', 'boolean'],
        ]);

        $before = [
            'enabled' => $this->settings->isEnabled('service_module.enabled'),
            'eligibility' => $this->settings->get('service_module.eligibility', 'onboarded_only'),
            'staff_delete_enabled' => $this->settings->isEnabled('service_module.staff_delete_enabled'),
            'deliverables_indicator_metadata_editable' => $this->settings->isEnabled('deliverables.indicator_metadata_editable'),
            'targets_allocation_editable' => $this->settings->isEnabled('targets.allocation_editable'),
        ];

        $after = [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'eligibility' => $validated['eligibility'],
            'staff_delete_enabled' => (bool) ($validated['staff_delete_enabled'] ?? false),
            'deliverables_indicator_metadata_editable' => (bool) ($validated['deliverables_indicator_metadata_editable'] ?? false),
            'targets_allocation_editable' => (bool) ($validated['targets_allocation_editable'] ?? false),
        ];

        $this->settings->setMany([
            'service_module.enabled' => $after['enabled'],
            'service_module.eligibility' => $after['eligibility'],
            'service_module.staff_delete_enabled' => $after['staff_delete_enabled'],
            'deliverables.indicator_metadata_editable' => $after['deliverables_indicator_metadata_editable'],
            'targets.allocation_editable' => $after['targets_allocation_editable'],
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
}
