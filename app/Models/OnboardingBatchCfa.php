<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingBatchCfa extends Model
{
    protected $table = 'onboarding_batch_cfa';

    protected $fillable = ['onboarding_batch_id', 'cfa_submission_id'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnboardingBatch::class, 'onboarding_batch_id');
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }
}
