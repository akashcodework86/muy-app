<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncubateeBill extends Model
{
    protected $fillable = [
        'onboarding_batch_id',
        'cfa_submission_id',
        'district_id',
        'bill_date',
        'amount',
        'bill_number',
        'document_disk',
        'document_path',
        'document_original_name',
        'document_mime',
        'document_size',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'amount' => 'decimal:2',
            'document_size' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnboardingBatch::class, 'onboarding_batch_id');
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasDocument(): bool
    {
        return is_string($this->document_path) && $this->document_path !== '';
    }
}
