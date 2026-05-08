<?php

namespace App\Http\Controllers\StateStaff;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\DistrictServiceSpoc;
use App\Models\OnboardingBatch;
use App\Models\ServiceCase;
use App\Models\ServiceCaseEvent;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Services\AppSettingsService;
use App\Services\LegacyApplicationServiceCaseSupport;
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
        private LegacyApplicationServiceCaseSupport $legacyApplications,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);

        $status = (string) $request->query('status', '');
        $districtId = (int) $request->query('district_id', 0);
        $batchId = (int) $request->query('batch_id', 0);
        $allowed = ['', ServiceCase::STATUS_PENDING_APPROVAL, ServiceCase::STATUS_SENT_BACK, ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = '';
        }

        $districtIds = $this->spocDistrictIds((int) $spoc->id);
        if ($districtId > 0 && ! in_array($districtId, $districtIds, true)) {
            $districtId = 0;
        }
        $legacyAppIds = $this->legacyApplicationIdsForSpocDistricts($districtIds);

        $scopeBase = ServiceCase::query()
            ->where(function ($outer) use ($districtIds, $legacyAppIds): void {
                $outer->whereHas('cfaSubmission', fn ($qq) => $qq->whereIn('district_id', $districtIds));
                if ($legacyAppIds !== []) {
                    $outer->orWhere(function ($qq) use ($legacyAppIds): void {
                        $qq->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyAppIds);
                    });
                }
            });

        if ($districtId > 0) {
            $legacyForOne = $this->legacyApplications->legacyApplicationIdsInLaravelDistrict($districtId);
            $scopeBase->where(function ($qq) use ($districtId, $legacyForOne): void {
                $qq->whereHas('cfaSubmission', fn ($q) => $q->where('district_id', $districtId));
                if ($legacyForOne !== []) {
                    $qq->orWhere(function ($q) use ($legacyForOne): void {
                        $q->whereNotNull('legacy_application_id')
                            ->whereNull('cfa_submission_id')
                            ->whereIn('legacy_application_id', $legacyForOne);
                    });
                }
            });
        }
        if ($batchId > 0) {
            $scopeBase->whereHas('cfaSubmission.onboardingBatchMembership', fn ($qq) => $qq->where('onboarding_batch_id', $batchId));
        }

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
            ->with([
                'cfaSubmission:id,applicant_name,application_no,district_id',
                'cfaSubmission.district:id,name',
                'cfaSubmission.onboardingBatchMembership:id,onboarding_batch_id,cfa_submission_id',
                'cfaSubmission.onboardingBatchMembership.batch:id,name,district_id',
                'service.category.parent',
                'submitter:id,name',
                'attachments:id,service_case_id,original_name,size_bytes,disk,path',
            ]);

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

        $cases->getCollection()->transform(function (ServiceCase $case) {
            if ($case->legacy_application_id && ! $case->cfa_submission_id) {
                $case->legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $case->legacy_application_id);
            }

            return $case;
        });

        $districtOptions = District::query()
            ->whereIn('id', $districtIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $batchOptions = OnboardingBatch::query()
            ->whereIn('district_id', $districtIds)
            ->whereHas('batchCfas.cfaSubmission', function ($qq) use ($districtIds): void {
                $qq->whereIn('district_id', $districtIds);
            })
            ->orderByDesc('id')
            ->get(['id', 'name', 'district_id']);

        if ($batchId > 0 && ! $batchOptions->contains(fn (OnboardingBatch $batch): bool => (int) $batch->id === $batchId)) {
            $batchId = 0;
        }

        return view('spoc.service-cases.index', [
            'cases' => $cases,
            'filterStatus' => $status,
            'filterDistrictId' => $districtId,
            'filterBatchId' => $batchId,
            'districtOptions' => $districtOptions,
            'batchOptions' => $batchOptions,
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

        $legacyIncubateePreview = null;
        if ($service_case->legacy_application_id && ! $service_case->cfa_submission_id) {
            $legacyIncubateePreview = $this->legacyApplications->incubateePreview((int) $service_case->legacy_application_id);
        }

        return view('spoc.service-cases.show', [
            'case' => $service_case,
            'legacyIncubateePreview' => $legacyIncubateePreview,
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

        return $this->redirectToQueue($request)
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

        return $this->redirectToQueue($request)
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

        return $this->redirectToQueue($request)
            ->with('status', 'Case rejected.');
    }

    public function downloadAttachment(Request $request, ServiceCase $service_case, int $attachment)
    {
        $this->ensureModuleOn();
        $spoc = $this->spocOrAbort($request);
        $this->assertCaseInSpocDistrict($service_case, (int) $spoc->id);
        $attachmentRecord = $service_case->attachments()->whereKey($attachment)->firstOrFail();

        $disk = Storage::disk((string) $attachmentRecord->disk);
        $stream = null;
        if ($disk->exists($attachmentRecord->path)) {
            $stream = $disk->readStream($attachmentRecord->path);
        } else {
            $path = ltrim((string) $attachmentRecord->path, '/');
            $prefixedPrivate = str_starts_with($path, 'private/') ? $path : 'private/'.$path;
            $prefixedPublic = str_starts_with($path, 'public/') ? $path : 'public/'.$path;

            foreach (['local', 'public'] as $fallbackDiskName) {
                $fallbackDisk = Storage::disk($fallbackDiskName);
                foreach ([$path, $prefixedPrivate, $prefixedPublic] as $candidate) {
                    if ($fallbackDisk->exists($candidate)) {
                        $stream = $fallbackDisk->readStream($candidate);
                        break 2;
                    }
                }
            }

            if (! is_resource($stream)) {
                $legacyPaths = [
                    storage_path('app/'.$path),
                    storage_path('app/private/'.$path),
                    storage_path('app/public/'.$path),
                    public_path('storage/'.$path),
                ];
                foreach ($legacyPaths as $legacyPath) {
                    if (is_file($legacyPath) && is_readable($legacyPath)) {
                        return response()->file($legacyPath, [
                            'Content-Disposition' => 'inline; filename="'.$attachmentRecord->original_name.'"',
                        ]);
                    }
                }
            }
        }

        abort_unless(is_resource($stream), 404);

        $ext = strtolower((string) pathinfo((string) $attachmentRecord->original_name, PATHINFO_EXTENSION));
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
            'Content-Disposition' => 'inline; filename="'.$attachmentRecord->original_name.'"',
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
        $districtId = $this->resolveCaseDistrictId($case);
        abort_unless($districtId > 0, 404);

        $allowed = DistrictServiceSpoc::query()
            ->where('state_staff_user_id', $spocUserId)
            ->where('district_id', $districtId)
            ->exists();

        abort_unless($allowed, 403);
    }

    private function resolveCaseDistrictId(ServiceCase $case): int
    {
        $case->loadMissing('cfaSubmission');
        if ($case->cfa_submission_id && $case->cfaSubmission) {
            return (int) $case->cfaSubmission->district_id;
        }

        if ($case->legacy_application_id) {
            $resolved = $this->legacyApplications->laravelDistrictIdForLegacyApplication((int) $case->legacy_application_id);

            return (int) ($resolved ?? 0);
        }

        return 0;
    }

    /**
     * @param  list<int>  $districtIds
     * @return list<int>
     */
    private function legacyApplicationIdsForSpocDistricts(array $districtIds): array
    {
        $out = [];
        foreach ($districtIds as $did) {
            foreach ($this->legacyApplications->legacyApplicationIdsInLaravelDistrict((int) $did) as $lid) {
                $out[] = $lid;
            }
        }

        return array_values(array_unique($out));
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

    private function redirectToQueue(Request $request): RedirectResponse
    {
        $target = trim((string) $request->input('redirect_to', ''));
        if ($target !== '' && $this->isSafeQueueUrl($target)) {
            return redirect()->to($target);
        }

        return redirect()->route('spoc.service-cases.index');
    }

    private function isSafeQueueUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        // Allow relative queue URLs from forms posted within this app.
        if (str_starts_with($url, '/')) {
            return str_contains($url, '/spoc/service-cases');
        }

        $target = parse_url($url);
        $current = parse_url(url('/'));
        if (! is_array($target) || ! is_array($current)) {
            return false;
        }

        $targetHost = strtolower((string) ($target['host'] ?? ''));
        $currentHost = strtolower((string) ($current['host'] ?? ''));
        if ($targetHost === '' || $currentHost === '' || $targetHost !== $currentHost) {
            return false;
        }

        $targetPath = (string) ($target['path'] ?? '');

        return str_contains($targetPath, '/spoc/service-cases');
    }

}
