<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditLogger;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controls whether district staff see the four "Training and capacity building"
 * menus and can open those routes.
 */
class StaffPhase3AttendanceNavController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private AdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('admin.staff-phase3-attendance-nav.edit', [
            'trainingPackage' => $this->settings->isEnabled('staff_nav.training_package.visible'),
            'technicalTraining' => $this->settings->isEnabled('staff_nav.technical_training.visible'),
            'eapEdpSession' => $this->settings->isEnabled('staff_nav.eap_edp_session.visible'),
            'districtWorkshop' => $this->settings->isEnabled('staff_nav.district_workshop.visible'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_package' => ['nullable', 'boolean'],
            'technical_training' => ['nullable', 'boolean'],
            'eap_edp_session' => ['nullable', 'boolean'],
            'district_workshop' => ['nullable', 'boolean'],
        ]);

        $asBool = fn (mixed $v): bool => filter_var($v, FILTER_VALIDATE_BOOLEAN);

        $before = [
            'training_package' => $this->settings->isEnabled('staff_nav.training_package.visible'),
            'technical_training' => $this->settings->isEnabled('staff_nav.technical_training.visible'),
            'eap_edp_session' => $this->settings->isEnabled('staff_nav.eap_edp_session.visible'),
            'district_workshop' => $this->settings->isEnabled('staff_nav.district_workshop.visible'),
        ];

        $after = [
            'training_package' => $asBool($validated['training_package'] ?? false),
            'technical_training' => $asBool($validated['technical_training'] ?? false),
            'eap_edp_session' => $asBool($validated['eap_edp_session'] ?? false),
            'district_workshop' => $asBool($validated['district_workshop'] ?? false),
        ];

        $this->settings->setMany([
            'staff_nav.training_package.visible' => $after['training_package'],
            'staff_nav.technical_training.visible' => $after['technical_training'],
            'staff_nav.eap_edp_session.visible' => $after['eap_edp_session'],
            'staff_nav.district_workshop.visible' => $after['district_workshop'],
        ], auth()->id());

        $this->auditLogger->record(
            $request,
            action: 'staff_nav.phase3_attendance.updated',
            subjectType: 'app_settings',
            subjectId: null,
            before: $before,
            after: $after,
            description: 'State admin updated district staff Phase 3 attendance menu visibility',
        );

        return redirect()
            ->route('admin.staff-phase3-attendance-nav.edit')
            ->with('status', 'District staff menu visibility updated.');
    }
}
