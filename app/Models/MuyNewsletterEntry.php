<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MuyNewsletterEntry extends Model
{
    public const MODULE_LABEL = 'MUY Newsletter (MIS 10.3)';

    protected $fillable = [
        'issue_date',
        'issue_edition',
        'title',
        'distribution_mode',
        'newsletter_url',
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
            'issue_date' => 'date',
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
