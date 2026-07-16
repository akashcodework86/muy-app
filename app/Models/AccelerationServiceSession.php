<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccelerationServiceSession extends Model
{
    public const MODULE_LABEL = 'MUY Acceleration Services — 7.2 Initiation';

    protected $fillable = [
        'service_date',
        'fiscal_year_id',
        'legacy_phase1_application_id',
        'incubatee_key',
        'incubatee_source',
        'applicant_name',
        'application_no',
        'phone',
        'district_name',
        'onboard_label',
        'counts_for_7_2',
        'is_draft',
        'submitted_by_user_id',
        'submitted_by_name',
        'status',
        'first_approved_by_user_id',
        'first_approved_by_name',
        'first_approved_at',
        'final_approved_by_user_id',
        'final_approved_by_name',
        'final_approved_at',
        'sent_back_by_user_id',
        'sent_back_by_name',
        'sent_back_at',
        'sent_back_remarks',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'counts_for_7_2' => 'boolean',
            'is_draft' => 'boolean',
            'first_approved_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'sent_back_at' => 'datetime',
        ];
    }

    /**
     * Approved entries are locked (view-only); follow-up services go on a new entry.
     */
    public function isLocked(): bool
    {
        return (string) $this->status === \App\Support\AccelerationServicesApproval::STATUS_APPROVED;
    }

    public function statusLabel(): string
    {
        return \App\Support\AccelerationServicesApproval::statusLabel($this->status);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccelerationServiceItem::class, 'session_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AccelerationServiceSessionEvent::class, 'session_id');
    }
}
