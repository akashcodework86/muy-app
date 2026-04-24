<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingBatch extends Model
{
    protected $fillable = [
        'hub_id',
        'district_id',
        'name',
        'target_size',
        'status',
        'locked_at',
        'onboarding_date',
        'pdf_deadline_extended_until',
        'pdf_compliance_waived',
        'edit_unlocked_at',
        'edit_unlocked_by_request_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'onboarding_date' => 'date',
            'pdf_deadline_extended_until' => 'datetime',
            'pdf_compliance_waived' => 'boolean',
            'edit_unlocked_at' => 'datetime',
        ];
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function draftCfas(): HasMany
    {
        return $this->hasMany(OnboardingBatchDraftCfa::class);
    }

    public function batchCfas(): HasMany
    {
        return $this->hasMany(OnboardingBatchCfa::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OnboardingBatchDocument::class);
    }

    public function editRequests(): HasMany
    {
        return $this->hasMany(OnboardingBatchEditRequest::class, 'onboarding_batch_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isEditUnlocked(): bool
    {
        return $this->isLocked() && $this->edit_unlocked_at !== null;
    }
}
