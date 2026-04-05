<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CfaSubmission extends Model
{
    protected $fillable = [
        'application_no',
        'fiscal_year_id',
        'district_id',
        'lgd_state_code',
        'lgd_district_code',
        'lgd_block_code',
        'referral_user_id',
        'applicant_name',
        'phone',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function referralUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referral_user_id');
    }

    public function onboardingBatchMembership(): HasOne
    {
        return $this->hasOne(OnboardingBatchCfa::class, 'cfa_submission_id');
    }

    public function draftBatchMembership(): HasOne
    {
        return $this->hasOne(OnboardingBatchDraftCfa::class, 'cfa_submission_id');
    }
}
