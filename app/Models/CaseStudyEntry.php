<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStudyEntry extends Model
{
    public const MODULE_LABEL = 'Case Studies & Testimonials (MIS 10.2)';

    protected $fillable = [
        'story_title',
        'story_type',
        'cfa_submission_id',
        'legacy_application_id',
        'incubatee_key',
        'incubatee_name',
        'application_no',
        'story_date',
        'document_disk',
        'document_path',
        'document_original_name',
        'remarks',
        'submitted_by_user_id',
        'submitted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'story_date' => 'date',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function hasDocument(): bool
    {
        return is_string($this->document_path) && $this->document_path !== '';
    }
}
