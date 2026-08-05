<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStudyShortlistRemark extends Model
{
    protected $fillable = ['case_study_shortlist_id', 'user_id', 'author_role', 'remark'];

    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(CaseStudyShortlist::class, 'case_study_shortlist_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
