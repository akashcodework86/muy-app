<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingBatchDocument extends Model
{
    protected $fillable = [
        'onboarding_batch_id',
        'doc_type',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnboardingBatch::class, 'onboarding_batch_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
