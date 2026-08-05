<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseStudyShortlist extends Model
{
    protected $fillable = [
        'candidate_key', 'person_key', 'source', 'source_application_id', 'program_year',
        'district_id', 'shortlist_month', 'applicant_name', 'application_no', 'block_name',
        'business_category', 'business_stage', 'gender', 'created_by_user_id',
        'removed_at', 'removed_by_user_id', 'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'shortlist_month' => 'date',
            'removed_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(CaseStudyShortlistRemark::class)->oldest();
    }
}
