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
 * Two knobs:
 *   - service_module.enabled       Master on/off for the workflow.
 *                                  When OFF: staff cannot CREATE new cases;
 *                                  existing draft / pending / sent_back cases
 *                                  remain editable so they can be closed out.
 *   - service_module.eligibility   Who staff can raise cases for:
 *                                    'all'             = any CFA submission in their district
 *                                    'onboarded_only'  = only CFAs linked to at least one
 *                                                        onboarding batch
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
}
