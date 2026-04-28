<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\DistrictServiceSpoc;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\ServiceCaseEvent;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Services\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
        $scopeBase = ServiceCase::query()
            ->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds));

        $tabCounts = [
            '' => (clone $scopeBase)->whereIn('status', [
                ServiceCase::STATUS_PENDING_APPROVAL,
                ServiceCase::STATUS_SENT_BACK,
                ServiceCase::STATUS_APPROVED,
                ServiceCase::STATUS_REJECTED,
            ])->count(),
            ServiceCase::STATUS_PENDING_APPROVAL => (clone $scopeBase)->where('status', ServiceCase::STATUS_PENDING_APPROVAL)->count(),
            ServiceCase::STATUS_SENT_BACK => (clone $scopeBase)->where('status', ServiceCase::STATUS_SENT_BACK)->count(),
            ServiceCase::STATUS_APPROVED => (clone $scopeBase)->where('status', ServiceCase::STATUS_APPROVED)->count(),
            ServiceCase::STATUS_REJECTED => (clone $scopeBase)->where('status', ServiceCase::STATUS_REJECTED)->count(),
        ];

        $q = (clone $scopeBase)
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
            'tabCounts' => $tabCounts,
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
        $this->notifyDistrictStaff($service_case, $spoc, 'approved');

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
        $this->notifyDistrictStaff($service_case, $spoc, 'sent_back');

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
        $this->notifyDistrictStaff($service_case, $spoc, 'rejected');

        return redirect()
            ->route('spoc.service-cases.show', $service_case)
            ->with('status', 'Case rejected.');
    }

    public function downloadAttachment(Request $request, ServiceCase $service_case, ServiceCaseAttachment $attachment)
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);
        abort_unless((int) $attachment->service_case_id === (int) $service_case->id, 404);

        $disk = Storage::disk((string) $attachment->disk);
        $stream = null;
        if ($disk->exists($attachment->path)) {
            $stream = $disk->readStream($attachment->path);
        } elseif ((string) $attachment->disk === 'local') {
            $legacyPaths = [
                storage_path('app/'.$attachment->path),
                storage_path('app/private/'.$attachment->path),
            ];
            foreach ($legacyPaths as $legacyPath) {
                if (is_file($legacyPath) && is_readable($legacyPath)) {
                    return response()->file($legacyPath, [
                        'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
                    ]);
                }
            }
        }

        abort_unless(is_resource($stream), 404);

        $ext = strtolower((string) pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
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

    private function notifyDistrictStaff(ServiceCase $serviceCase, User $spoc, string $action): void
    {
        $serviceCase->loadMissing(['service:id,name', 'cfaSubmission:id,application_no,applicant_name']);
        $recipientIds = collect([
            (int) ($serviceCase->submitted_by ?? 0),
            (int) ($serviceCase->created_by ?? 0),
        ])->filter(fn (int $id) => $id > 0)->unique()->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $statusLabel = str_replace('_', ' ', (string) $serviceCase->status);
        $serviceName = (string) ($serviceCase->service?->name ?? 'Service case');
        $appNo = (string) ($serviceCase->cfaSubmission?->application_no ?? '');
        $applicant = (string) ($serviceCase->cfaSubmission?->applicant_name ?? '');
        $note = match ($action) {
            'sent_back' => (string) ($serviceCase->sent_back_note ?? ''),
            'rejected' => (string) ($serviceCase->rejected_note ?? ''),
            default => '',
        };
        $title = match ($action) {
            'approved' => 'Service approved',
            'sent_back' => 'Service sent back',
            'rejected' => 'Service rejected',
            default => 'Service update',
        };

        $body = trim($serviceName.' is '.$statusLabel.' by '.$spoc->name.'.');
        if ($appNo !== '' || $applicant !== '') {
            $body .= ' CFA '.trim($appNo.' '.$applicant).'.';
        }

        Notification::send($recipients, new ServiceCaseWorkflowNotification([
            'title' => $title,
            'body' => $body,
            'service_case_id' => (int) $serviceCase->id,
            'cfa_submission_id' => (int) ($serviceCase->cfa_submission_id ?? 0),
            'status' => (string) $serviceCase->status,
            'spoc_name' => (string) $spoc->name,
            'service_name' => $serviceName,
            'application_no' => $appNo,
            'incubatee_name' => $applicant,
            'comment' => $note !== '' ? $note : null,
            'action' => $action,
        ]));
    }
}

