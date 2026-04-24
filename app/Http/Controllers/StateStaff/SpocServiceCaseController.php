<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\DistrictServiceSpoc;
use App\Models\ServiceCase;
use App\Models\ServiceCaseEvent;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpocServiceCaseController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);

        $status = (string) $request->query('status', '');
        $allowed = ['', ServiceCase::STATUS_PENDING_APPROVAL, ServiceCase::STATUS_SENT_BACK, ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $districtIds = $this->spocDistrictIds((int) $spoc->id);
        $q = ServiceCase::query()
            ->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds))
            ->with(['cfaSubmission:id,applicant_name,application_no,district_id', 'service.category.parent', 'submitter:id,name']);

        if ($status !== '') {
            $q->where('status', $status);
        } else {
            $q->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ]);
        }

        $cases = $q->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('spoc.service-cases.index', [
            'cases' => $cases,
            'filterStatus' => $status,
        ]);
    }

    public function show(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        $service_case->load([
            'cfaSubmission.district',
            'service.category.parent',
            'attachments.uploader',
            'events.user',
            'submitter',
            'approver',
            'rejector',
        ]);

        return view('spoc.service-cases.show', [
            'case' => $service_case,
        ]);
    }

    public function approve(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be approved.']);
        }

        DB::transaction(function () use ($service_case, $spoc): void {
            $service_case->status = ServiceCase::STATUS_APPROVED;
            $service_case->approved_at = now();
            $service_case->approved_by = (int) $spoc->id;
            $service_case->completed_at = now();
            $service_case->sent_back_note = null;
            $service_case->rejected_at = null;
            $service_case->rejected_by = null;
            $service_case->rejected_note = null;
            $service_case->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $service_case->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_approved',
                'meta' => null,
            ]);
        });

        return redirect()
            ->route('spoc.service-cases.show', $service_case)
            ->with('status', 'Case approved.');
    }

    public function sendBack(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be sent back.']);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($service_case, $spoc, $validated): void {
            $service_case->status = ServiceCase::STATUS_SENT_BACK;
            $service_case->sent_back_note = trim((string) $validated['note']);
            $service_case->approved_at = null;
            $service_case->approved_by = null;
            $service_case->rejected_at = null;
            $service_case->rejected_by = null;
            $service_case->rejected_note = null;
            $service_case->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $service_case->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_sent_back',
                'meta' => ['note' => $service_case->sent_back_note],
            ]);
        });

        return redirect()
            ->route('spoc.service-cases.show', $service_case)
            ->with('status', 'Case sent back to district staff.');
    }

    public function reject(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);

        if ($service_case->status !== ServiceCase::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['status' => 'Only pending approval cases can be rejected.']);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($service_case, $spoc, $validated): void {
            $service_case->status = ServiceCase::STATUS_REJECTED;
            $service_case->rejected_at = now();
            $service_case->rejected_by = (int) $spoc->id;
            $service_case->rejected_note = trim((string) $validated['note']);
            $service_case->approved_at = null;
            $service_case->approved_by = null;
            $service_case->sent_back_note = null;
            $service_case->save();

            ServiceCaseEvent::query()->create([
                'service_case_id' => $service_case->id,
                'user_id' => (int) $spoc->id,
                'action' => 'spoc_rejected',
                'meta' => ['note' => $service_case->rejected_note],
            ]);
        });

        return redirect()
            ->route('spoc.service-cases.show', $service_case)
            ->with('status', 'Case rejected.');
    }

    private function ensureModuleOn(): void
    {
        abort_unless(
            $this->settings->isEnabled('service_module.enabled'),
            403,
            'The service module is turned off. Ask your state admin to enable it under Admin → More → Service module settings.'
        );
    }

    private function spocOrAbort(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'state_staff', 403);

        return $u;
    }

    /**
     * @return list<int>
     */
    private function spocDistrictIds(int $spocUserId): array
    {
        return DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    private function assertCaseInSpocDistrict(ServiceCase $case, int $spocUserId): void
    {
        $case->loadMissing('cfaSubmission');
        $districtId = (int) ($case->cfaSubmission?->district_id ?? 0);
        abort_unless($districtId > 0, 404);

        $allowed = DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->where('district_id', $districtId)
            ->exists();

        abort_unless($allowed, 403);
    }
}

