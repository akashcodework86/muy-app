<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\Service;
use App\Models\ServiceCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceCaseRecorder
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function create(CfaSubmission $submission, Service $service, ?int $createdById, array $rawPayload = []): ServiceCase
    {
        if (! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => 'This service is inactive.']);
        }

        $existing = ServiceCase::query()
            ->where('cfa_submission_id', $submission->id)
            ->where('service_id', $service->id)
            ->first();

        if (! $service->allows_multiple && $existing !== null) {
            throw ValidationException::withMessages([
                'service_id' => 'This service allows only one case per incubatee. Use the existing case or pick a different service.',
            ]);
        }

        $payload = $this->normalizePayload($service, $rawPayload);

        return ServiceCase::query()->create([
            'cfa_submission_id' => $submission->id,
            'service_id' => $service->id,
            'status' => ServiceCase::STATUS_OPEN,
            'payload' => $payload === [] ? null : $payload,
            'reference_number' => null,
            'completed_at' => null,
            'created_by' => $createdById,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function complete(ServiceCase $case, array $attributes): void
    {
        if ($case->status === ServiceCase::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['status' => 'This case is already completed.']);
        }

        if ($case->status === ServiceCase::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['status' => 'Cancelled cases cannot be completed.']);
        }

        Validator::make($attributes, [
            'reference_number' => ['nullable', 'string', 'max:191'],
        ])->validate();

        $case->loadMissing('service');
        $service = $case->service;
        if ($service === null) {
            throw ValidationException::withMessages(['service' => 'Service is missing for this case.']);
        }

        $extraPayload = [];
        if (! empty($attributes['payload']) && is_array($attributes['payload'])) {
            $extraPayload = $this->normalizePayload($service, $attributes['payload']);
        }

        $merged = array_merge($case->payload ?? [], $extraPayload);

        $case->status = ServiceCase::STATUS_COMPLETED;
        $case->reference_number = $attributes['reference_number'] ?? $case->reference_number;
        $case->payload = $merged === [] ? null : $merged;
        $case->completed_at = now();
        $case->save();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizePayload(Service $service, array $raw): array
    {
        $schema = $service->field_schema;
        if (! is_array($schema) || $schema === []) {
            return [];
        }

        $out = [];
        foreach ($schema as $row) {
            if (! is_array($row) || empty($row['key']) || ! is_string($row['key'])) {
                continue;
            }
            $key = $row['key'];
            $v = $raw[$key] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                continue;
            }
            $out[$key] = is_string($v) ? trim($v) : $v;
        }

        return $out;
    }
}
