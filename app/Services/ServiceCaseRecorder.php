<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\DistrictServiceSpoc;
use App\Models\Service;
use App\Models\ServiceCase;
use App\Models\ServiceCaseAttachment;
use App\Models\ServiceCaseEvent;
use App\Models\User;
use App\Notifications\ServiceCaseWorkflowNotification;
use App\Support\BusinessDays;
use App\Support\ConvergenceReapSupport;
use App\Support\ServiceFieldTypes;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceCaseRecorder
{
    public function __construct(
        private SchemaValidator $schemaValidator,
        private LegacyApplicationServiceCaseSupport $legacyApplications,
    ) {}

    /**
     * Legacy entry point: creates a draft case (optionally with an early payload snapshot).
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function create(CfaSubmission $submission, Service $service, ?int $createdById, array $rawPayload = []): ServiceCase
    {
        $case = $this->createDraft($submission, $service, $createdById);

        if ($rawPayload !== []) {
            $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
            $payload = $schema === []
                ? []
                : $this->schemaValidator->validate($schema, $rawPayload);
            $payload = ConvergenceReapSupport::mergeThroughReapIntoPayload($service, $payload, $rawPayload);
            $case->payload = $payload === [] ? null : $payload;
            ConvergenceReapSupport::syncThroughReapColumn($case, $payload);
            $case->save();
        }

        return $case->fresh();
    }

    public function createDraft(CfaSubmission $submission, Service $service, ?int $createdById): ServiceCase
    {
        if (! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => 'This service is inactive.']);
        }

        $existing = ServiceCase::query()
            ->where('cfa_submission_id', $submission->id)
            ->where('service_id', $service->id);

        if (! $service->allows_multiple && $existing->exists()) {
            throw ValidationException::withMessages([
                'service_id' => 'This service allows only one case per incubatee. This incubatee already has this service assigned.',
            ]);
        }

        $case = ServiceCase::query()->create([
            'cfa_submission_id' => $submission->id,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_DRAFT,
            'payload' => null,
            'reference_number' => null,
            'delivered_on' => null,
            'completed_at' => null,
            'created_by' => $createdById,
        ]);

        $this->recordEvent($case, $createdById, 'draft_created', []);

        return $case;
    }

    /**
     * Phase 2 (rbiphase2) incubatee with no local mirrored CFA — stored on {@see ServiceCase::$legacy_application_id}.
     */
    public function createLegacyDraft(int $legacyApplicationId, Service $service, User $staff): ServiceCase
    {
        if (! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => 'This service is inactive.']);
        }

        if (! ServiceCase::supportsLegacyApplicationLink()) {
            throw ValidationException::withMessages([
                'legacy_application_id' => 'Legacy application–linked cases are not enabled until the server database is updated (run migrations). Use a Phase 3 CFA incubatee instead.',
            ]);
        }

        $this->legacyApplications->assertLegacyApplicationInStaffDistrict($staff, $legacyApplicationId);

        $existing = ServiceCase::query()
            ->where('legacy_application_id', $legacyApplicationId)
            ->where('service_id', $service->id);

        if (! $service->allows_multiple && $existing->exists()) {
            throw ValidationException::withMessages([
                'service_id' => 'This service allows only one case per incubatee. This incubatee already has this service assigned.',
            ]);
        }

        $case = ServiceCase::query()->create([
            'cfa_submission_id' => null,
            'legacy_application_id' => $legacyApplicationId,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_DRAFT,
            'payload' => null,
            'reference_number' => null,
            'delivered_on' => null,
            'completed_at' => null,
            'created_by' => (int) $staff->id,
        ]);

        $this->recordEvent($case, (int) $staff->id, 'draft_created', ['legacy_application_id' => $legacyApplicationId]);

        return $case;
    }

    /**
     * Staff submits a draft or re-submits after send-back.
     *
     * @param  array<string, mixed>  $attributes  actor_id, reference_number?, delivered_on?, payload?
     * @param  list<UploadedFile>  $uploads
     */
    public function submit(ServiceCase $case, array $attributes, array $uploads = []): void
    {
        $actorId = (int) ($attributes['actor_id'] ?? 0);
        if ($actorId < 1) {
            throw ValidationException::withMessages(['actor_id' => 'Actor is required.']);
        }

        if (! in_array($case->status, [ServiceCase::STATUS_DRAFT, ServiceCase::STATUS_SENT_BACK], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or sent-back cases can be submitted.']);
        }

        $case->loadMissing(['service', 'cfaSubmission']);
        $service = $case->service;
        if ($service === null) {
            throw ValidationException::withMessages(['service' => 'Service is missing for this case.']);
        }

        if (! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => 'This service is inactive.']);
        }

        Validator::make($attributes, [
            'reference_number' => ['nullable', 'string', 'max:191'],
            'delivered_on' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ])->validate();

        $schema = ServiceFieldTypes::normalizeSchema($service->field_schema ?? []);
        $rawPayload = is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [];
        $payload = $schema === []
            ? []
            : $this->schemaValidator->validate($schema, $rawPayload);
        $payload = ConvergenceReapSupport::mergeThroughReapIntoPayload($service, $payload, $rawPayload);

        $requiresApproval = (bool) $service->requires_approval;
        $districtId = $this->districtIdForCase($case);
        if ($requiresApproval && (! $districtId || $districtId < 1)) {
            throw ValidationException::withMessages([
                'district' => 'Cannot route this case to a SPOC — district could not be resolved. Ask state admin to align legacy district names with this MIS.',
            ]);
        }

        $deliveredOn = $attributes['delivered_on'] ?? null;

        if (! $requiresApproval) {
            if ($deliveredOn === null || $deliveredOn === '') {
                $deliveredOn = now()->toDateString();
            }
        } else {
            $deliveredOn = null;
        }

        $existingFiles = $case->attachments()->count();
        if ($existingFiles + count($uploads) > 3) {
            throw ValidationException::withMessages([
                'attachments' => 'Maximum 3 documents per case.',
            ]);
        }

        if ($service->requires_document && $existingFiles + count($uploads) < 1) {
            throw ValidationException::withMessages([
                'attachments' => 'This service requires at least one document.',
            ]);
        }

        foreach ($uploads as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw ValidationException::withMessages(['attachments' => 'Invalid upload.']);
            }
            $this->assertAllowedUpload($service, $file);
        }

        DB::transaction(function () use ($case, $service, $attributes, $payload, $requiresApproval, $deliveredOn, $actorId, $uploads): void {
            foreach ($uploads as $file) {
                $this->persistAttachment($case, $file, $actorId);
            }

            if ($service->requires_document && $case->attachments()->count() < 1) {
                throw ValidationException::withMessages([
                    'attachments' => 'This service requires at least one document.',
                ]);
            }

            $case->payload = $payload === [] ? null : $payload;
            ConvergenceReapSupport::syncThroughReapColumn($case, is_array($case->payload) ? $case->payload : []);
            $case->reference_number = $attributes['reference_number'] ?? $case->reference_number;
            $case->delivered_on = $deliveredOn ? Carbon::parse((string) $deliveredOn)->startOfDay() : null;
            $case->submitted_at = now();
            $case->submitted_by = $actorId;
            $case->approval_snapshot = [
                'requires_approval' => $requiresApproval,
                'requires_document' => (bool) $service->requires_document,
                'captured_at' => now()->toIso8601String(),
            ];
            $case->sent_back_note = null;
            $case->rejected_at = null;
            $case->rejected_by = null;
            $case->rejected_note = null;

            if ($requiresApproval) {
                $case->status = ServiceCase::STATUS_PENDING_APPROVAL;
                $case->spoc_user_id = $this->resolveSpocUserId($this->districtIdForCase($case));
                $case->sla_deadline_at = BusinessDays::add(now(), 3);
                $case->approved_at = null;
                $case->approved_by = null;
            } else {
                $case->status = ServiceCase::STATUS_APPROVED;
                $case->spoc_user_id = null;
                $case->sla_deadline_at = null;
                $case->approved_at = now();
                $case->approved_by = $actorId;
                $case->completed_at = now();
            }

            $case->save();

            $this->recordEvent($case, $actorId, $requiresApproval ? 'submitted_pending_approval' : 'submitted_auto_approved', [
                'spoc_user_id' => $case->spoc_user_id,
            ]);
        });

        if ($requiresApproval && (int) ($case->spoc_user_id ?? 0) > 0) {
            $case->loadMissing(['service:id,name', 'cfaSubmission:id,application_no,applicant_name']);
            $spoc = User::query()
                ->whereKey((int) $case->spoc_user_id)
                ->where('is_active', true)
                ->first();
            if ($spoc) {
                Notification::send($spoc, new ServiceCaseWorkflowNotification([
                    'title' => 'Service case pending approval',
                    'body' => trim(($case->service?->name ?? 'Service case').' is waiting for your review.'),
                    'service_case_id' => (int) $case->id,
                    'cfa_submission_id' => (int) ($case->cfa_submission_id ?? 0),
                    'status' => (string) $case->status,
                    'service_name' => (string) ($case->service?->name ?? ''),
                    'application_no' => (string) ($case->cfaSubmission?->application_no ?? ''),
                    'incubatee_name' => (string) ($case->cfaSubmission?->applicant_name ?? ''),
                    'action' => 'pending_approval',
                ]));
            }
        }
    }

    /**
     * @deprecated Prefer {@see submit()}. Kept for backward compatibility with the legacy CFA detail partial.
     *
     * @param  array<string, mixed>  $attributes
     */
    /**
     * @param  list<UploadedFile>  $uploads
     */
    public function complete(ServiceCase $case, array $attributes, array $uploads = []): void
    {
        $actorId = (int) ($attributes['actor_id'] ?? $case->created_by ?? 0);
        $this->submit($case, [
            'actor_id' => $actorId,
            'reference_number' => $attributes['reference_number'] ?? null,
            'delivered_on' => $attributes['delivered_on'] ?? null,
            'payload' => is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [],
        ], $uploads);
    }

    public function staffDelete(ServiceCase $case, int $actorId): void
    {
        if (! $case->canBeDeletedByStaff()) {
            throw ValidationException::withMessages(['status' => 'This case cannot be deleted in its current state.']);
        }

        DB::transaction(function () use ($case): void {
            foreach ($case->attachments as $attachment) {
                $attachment->deleteFileIfLocal();
            }

            $case->delete();
        });
    }

    private function resolveSpocUserId(?int $districtId): ?int
    {
        if (! $districtId) {
            return null;
        }

        $row = DistrictServiceSpoc::query()->where('district_id', $districtId)->first();

        return $row?->state_staff_user_id;
    }

    private function districtIdForCase(ServiceCase $case): ?int
    {
        if ($case->cfa_submission_id) {
            $case->loadMissing('cfaSubmission');

            return $case->cfaSubmission ? (int) $case->cfaSubmission->district_id : null;
        }

        if ($case->legacy_application_id) {
            return $this->legacyApplications->laravelDistrictIdForLegacyApplication((int) $case->legacy_application_id);
        }

        return null;
    }

    private function assertAllowedUpload(Service $service, UploadedFile $file): void
    {
        $maxKb = 5120;
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Each file must be 5 MB or smaller.']);
        }

        $allowedTags = $service->effectiveAllowedDocumentTypes();
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $okPdf = in_array('pdf', $allowedTags, true)
            && (str_contains($mime, 'pdf') || $ext === 'pdf');
        $okImage = in_array('image', $allowedTags, true)
            && (
                str_starts_with($mime, 'image/')
                || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
            );

        if (! $okPdf && ! $okImage) {
            throw ValidationException::withMessages([
                'attachments' => 'Only PDF and image uploads are allowed for this service.',
            ]);
        }
    }

    private function persistAttachment(ServiceCase $case, UploadedFile $file, int $uploadedBy): void
    {
        $dir = 'service-case-attachments/'.$case->id;
        $path = $file->store($dir, 'local');

        ServiceCaseAttachment::query()->create([
            'service_case_id' => $case->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName() ?: 'upload',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function recordEvent(ServiceCase $case, ?int $userId, string $action, array $meta): void
    {
        ServiceCaseEvent::query()->create([
            'service_case_id' => $case->id,
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
