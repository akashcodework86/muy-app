<?php

namespace App\Models;

use App\Support\ConvergenceReapSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ServiceCase extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT_BACK = 'sent_back';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Legacy constant; DB rows were migrated to {@see STATUS_DRAFT}. */
    public const STATUS_OPEN = 'open';

    /** @deprecated Legacy constant; DB rows were migrated to {@see STATUS_APPROVED}. */
    public const STATUS_COMPLETED = 'completed';

    /** Statuses that block creating another case when allows_multiple is false. */
    public const BLOCKING_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_SENT_BACK,
    ];

    /**
     * True when the `service_cases.legacy_application_id` column exists (Phase 2 bridge migration applied).
     */
    public static function supportsLegacyApplicationLink(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        return $cached = Schema::hasColumn((new static)->getTable(), 'legacy_application_id');
    }

    protected $fillable = [
        'cfa_submission_id',
        'legacy_application_id',
        'service_id',
        'status',
        'payload',
        'through_reap',
        'reference_number',
        'delivered_on',
        'completed_at',
        'submitted_at',
        'submitted_by',
        'spoc_user_id',
        'approval_snapshot',
        'sent_back_note',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejected_note',
        'udyam_registration_type',
        'sla_deadline_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'through_reap' => 'boolean',
            'approval_snapshot' => 'array',
            'delivered_on' => 'date',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sla_deadline_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ServiceCase $case): void {
            if (! Schema::hasColumn($case->getTable(), 'through_reap') || ! $case->isDirty('payload')) {
                return;
            }

            $case->loadMissing('service.category');
            $payload = is_array($case->payload) ? $case->payload : [];
            if (ConvergenceReapSupport::serviceUsesReapWorkflow($case->service)) {
                ConvergenceReapSupport::syncThroughReapColumn($case, $payload);
            } else {
                $case->through_reap = false;
            }
        });
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function spoc(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spoc_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCaseEvent::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceCaseAttachment::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Service date shown in reporting lists/exports.
     *
     * Some services capture this as a schema field in payload while older or
     * generic services use the dedicated delivered_on column.
     */
    public function serviceDateForReporting(): ?Carbon
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $candidate = $payload['service_date'] ?? null;

        if ($candidate === null || $candidate === '') {
            $schema = is_array($this->service?->field_schema) ? $this->service->field_schema : [];
            foreach ($schema as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $label = mb_strtolower(trim((string) ($field['label'] ?? '')));
                $key = trim((string) ($field['key'] ?? ''));
                if ($label === 'service date' && $key !== '' && isset($payload[$key]) && $payload[$key] !== '') {
                    $candidate = $payload[$key];
                    break;
                }
            }
        }

        $candidate = ($candidate === null || $candidate === '') ? $this->delivered_on : $candidate;
        if (! $candidate) {
            return null;
        }

        try {
            return $candidate instanceof Carbon ? $candidate : Carbon::parse((string) $candidate);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Backward-compatible alias — "completed" in the old schema is now "approved".
     */
    public function isCompleted(): bool
    {
        return $this->isApproved();
    }

    public function canBeDeletedByStaff(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_SENT_BACK,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function isConvergenceServiceCase(): bool
    {
        $this->loadMissing('service.category');

        return ConvergenceReapSupport::serviceIsConvergence($this->service);
    }

    public function isReapSupportServiceCase(): bool
    {
        $this->loadMissing('service');

        return ConvergenceReapSupport::serviceIsReapSupportService($this->service);
    }

    public function displaysReapSupportRoute(): bool
    {
        return $this->isReapSupportServiceCase()
            || ($this->isConvergenceServiceCase() && $this->isMarkedThroughReap());
    }

    public function isMarkedThroughReap(): bool
    {
        if ($this->isReapSupportServiceCase()) {
            return true;
        }

        if (Schema::hasColumn($this->getTable(), 'through_reap') && $this->through_reap) {
            return true;
        }

        $payload = is_array($this->payload) ? $this->payload : [];

        return ConvergenceReapSupport::payloadValueIsThroughReap(
            $payload[ConvergenceReapSupport::PAYLOAD_KEY] ?? null,
        );
    }

    /**
     * @return Collection<int, ServiceCaseAttachment>
     */
    public function reapAttachments(): Collection
    {
        $this->loadMissing('attachments');

        if (! $this->isMarkedThroughReap()) {
            return collect();
        }

        $payload = is_array($this->payload) ? $this->payload : [];
        $reapName = trim((string) ($payload[ConvergenceReapSupport::REAP_DOCUMENT_KEY] ?? ''));
        if ($reapName === '') {
            return collect();
        }

        return $this->attachments->filter(
            fn (ServiceCaseAttachment $attachment) => strcasecmp((string) $attachment->original_name, $reapName) === 0
        )->values();
    }

    /**
     * Convergence / general service attachments, excluding the REAP upload when Through REAP is set.
     *
     * @return Collection<int, ServiceCaseAttachment>
     */
    public function convergenceAttachments(): Collection
    {
        $this->loadMissing('attachments');

        if (! $this->isMarkedThroughReap()) {
            return $this->attachments->values();
        }

        $reapIds = $this->reapAttachments()->pluck('id')->all();

        return $this->attachments->reject(
            fn (ServiceCaseAttachment $attachment) => in_array($attachment->id, $reapIds, true)
        )->values();
    }
}
