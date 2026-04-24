<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingBatchEditRequest extends Model
{
    protected $fillable = [
        'onboarding_batch_id',
        'hub_id',
        'district_id',
        'requested_by',
        'reason',
        'expected_changes',
        'status',
        'approved_by',
        'approved_at',
        'relocked_by',
        'relocked_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'relocked_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnboardingBatch::class, 'onboarding_batch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relocked_by');
    }
}

