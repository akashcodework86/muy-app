<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CfaSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\User;
use App\Services\AppSettingsService;
use App\Services\ServiceCaseRecorder;
use App\Support\ServiceFieldTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffServiceCaseController extends Controller
{
    public function __construct(
        private AppSettingsService $settings,
        private ServiceCaseRecorder $recorder,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        $status = (string) $request->query('status', '');
        $allowed = ['', ServiceCase::STATUS_DRAFT, ServiceCase::STATUS_PENDING_APPROVAL, ServiceCase::STATUS_SENT_BACK, ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $q = ServiceCase::query()
            ->whereHas('cfaSubmission', fn ($qq) => $qq->where('district_id', (int) $staff->district_id))
            ->with(['cfaSubmission:id,applicant_name,application_no,district_id', 'service.category.parent']);

        if ($status !== '') {
            $q->where('status', $status);
        }

        $cases = $q->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('staff.services.index', [
            'cases' => $cases,
            'filterStatus' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        $services = $this->pickerServices();

        $eligible = $this->eligibleSubmissions($staff);
        $prefillId = (int) $request->query('cfa_submission_id', 0);
        if ($prefillId > 0 && ! (clone $eligible)->whereKey($prefillId)->exists()) {
            $prefillId = 0;
        }

        return view('staff.services.create', [
            'submissions' => $eligible->get(),
            'defaultCfaSubmissionId' => $prefillId,
            'services' => $services,
            'servicesJson' => $services->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'requires_document' => (bool) $s->requires_document,
                'requires_approval' => (bool) $s->requires_approval,
                'schema' => ServiceFieldTypes::normalizeSchema($s->field_schema ?? []),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);

        $validated = $request->validate([
            'cfa_submission_id' => ['required', 'integer', 'exists:cfa_submissions,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload_files' => ['nullable', 'array'],
            'payload_files.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $submission = CfaSubmission::query()->findOrFail((int) $validated['cfa_submission_id']);
        $this->assertSubmissionEligible($staff, $submission);

        $service = Service::query()->whereKey((int) $validated['service_id'])->firstOrFail();
        if (! $service->is_active) {
            return back()->withErrors(['service_id' => 'This service is inactive.'])->withInput();
        }

        $payload = is_array($validated['payload'] ?? null) ? $validated['payload'] : [];
        $uploads = array_values($request->file('attachments', []));

        // Schema file fields upload into case attachments and store filename in payload.
        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $fileKeys = collect($schema)
            ->filter(fn (array $field) => ($field['type'] ?? null) === ServiceFieldTypes::FILE)
            ->map(fn (array $field) => (string) ($field['key'] ?? ''))
            ->filter(fn (string $key) => $key !== '')
            ->values();

        foreach ($fileKeys as $key) {
            $file = $request->file('payload_files.'.$key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $payload[$key] = $file->getClientOriginalName();
                $uploads[] = $file;
            }
        }

        try {
            $case = $this->recorder->createDraft($submission, $service, (int) $staff->id);
            $this->recorder->submit($case, [
                'actor_id' => (int) $staff->id,
                'reference_number' => $validated['reference_number'] ?? null,
                'delivered_on' => $validated['delivered_on'] ?? null,
                'payload' => $payload,
            ], $uploads);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Service case submitted.');
    }

    public function show(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);

        $service_case->load([
            'cfaSubmission.district',
            'service.category.parent',
            'attachments.uploader',
            'events.user',
        ]);

        return view('staff.services.show', [
            'case' => $service_case,
        ]);
    }

    public function edit(Request $request, ServiceCase $service_case): View
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        abort_unless($this->canEditByStaff($service_case), 403, 'This case cannot be edited now.');

        $service_case->load([
            'cfaSubmission:id,applicant_name,application_no,district_id',
            'service.category.parent',
            'attachments',
        ]);

        return view('staff.services.edit', [
            'case' => $service_case,
            'schema' => ServiceFieldTypes::normalizeSchema($service_case->service?->field_schema ?? []),
            'payload' => is_array($service_case->payload) ? $service_case->payload : [],
        ]);
    }

    public function update(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);
        abort_unless($this->canEditByStaff($service_case), 403, 'This case cannot be edited now.');

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload_files' => ['nullable', 'array'],
            'payload_files.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $service_case->loadMissing('service');
        $service = $service_case->service;
        if (! $service || ! $service->is_active) {
            return back()->withErrors(['service' => 'This service is inactive.'])->withInput();
        }

        $payload = is_array($validated['payload'] ?? null) ? $validated['payload'] : [];
        $uploads = array_values($request->file('attachments', []));
        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $fileKeys = collect($schema)
            ->filter(fn (array $field) => ($field['type'] ?? null) === ServiceFieldTypes::FILE)
            ->map(fn (array $field) => (string) ($field['key'] ?? ''))
            ->filter(fn (string $key) => $key !== '')
            ->values();

        foreach ($fileKeys as $key) {
            $file = $request->file('payload_files.'.$key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $payload[$key] = $file->getClientOriginalName();
                $uploads[] = $file;
            }
        }

        try {
            $this->recorder->submit($service_case, [
                'actor_id' => (int) $staff->id,
                'reference_number' => $validated['reference_number'] ?? null,
                'delivered_on' => $validated['delivered_on'] ?? null,
                'payload' => $payload,
            ], $uploads);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Service case updated.');
    }

    public function destroy(Request $request, ServiceCase $service_case): RedirectResponse
    {
        $this->ensureModuleOn();
        $staff = $this->staffOrAbort($request);
        $this->assertCaseInDistrict($staff, $service_case);

        try {
            $this->recorder->staffDelete($service_case, (int) $staff->id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.services.index')
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('staff.services.index')
            ->with('status', 'Case deleted.');
    }

    private function ensureModuleOn(): void
    {
        abort_unless(
            $this->settings->isEnabled('service_module.enabled'),
            403,
            'The service module is turned off. Ask your state admin to enable it under Admin → More → Service module settings.'
        );
    }

    private function staffOrAbort(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->role === 'district_staff' && (int) $u->district_id > 0, 403);

        return $u;
    }

    private function eligibleSubmissions(User $staff)
    {
        $q = CfaSubmission::query()
            ->where('district_id', (int) $staff->district_id)
            ->orderBy('applicant_name');

        if ($this->settings->get('service_module.eligibility', 'onboarded_only') === 'onboarded_only') {
            $q->whereHas('onboardingBatchMembership');
        }

        return $q;
    }

    private function assertSubmissionEligible(User $staff, CfaSubmission $submission): void
    {
        abort_unless((int) $submission->district_id === (int) $staff->district_id, 403);
        if ($this->settings->get('service_module.eligibility', 'onboarded_only') === 'onboarded_only'
            && ! $submission->onboardingBatchMembership()->exists()) {
            throw ValidationException::withMessages([
                'cfa_submission_id' => 'This incubatee is not in an onboarding batch yet.',
            ]);
        }
    }

    private function assertCaseInDistrict(User $staff, ServiceCase $case): void
    {
        $case->loadMissing('cfaSubmission');
        abort_unless(
            $case->cfaSubmission && (int) $case->cfaSubmission->district_id === (int) $staff->district_id,
            403
        );
    }

    private function canEditByStaff(ServiceCase $case): bool
    {
        return in_array($case->status, [
            ServiceCase::STATUS_DRAFT,
            ServiceCase::STATUS_SENT_BACK,
        ], true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Service>
     */
    private function pickerServices()
    {
        return Service::query()
            ->where('is_active', true)
            ->with(['category.parent'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Service $s) => $s->category && $s->category->parent_id !== null)
            ->values();
    }
}
