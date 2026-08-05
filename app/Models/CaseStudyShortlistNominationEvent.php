<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStudyShortlistNominationEvent extends Model
{
    protected $fillable = [
        'case_study_shortlist_nomination_id', 'action', 'from_status', 'to_status', 'note', 'actor_user_id',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(CaseStudyShortlistNomination::class, 'case_study_shortlist_nomination_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
