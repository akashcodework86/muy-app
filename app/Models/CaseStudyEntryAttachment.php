<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseStudyEntryAttachment extends Model
{
    protected $fillable = [
        'case_study_entry_id',
        'attachment_type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'sort_order',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CaseStudyEntry::class, 'case_study_entry_id');
    }

    public function isImage(): bool
    {
        if ($this->attachment_type === 'image') {
            return true;
        }

        return str_starts_with((string) $this->mime, 'image/');
    }
}
