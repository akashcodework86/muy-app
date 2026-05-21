<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketLinkagePartner extends Model
{
    protected $fillable = [
        'market_linkage_submission_id',
        'partner_name',
        'linkage_mode',
        'linkage_date',
        'document_disk',
        'document_path',
        'document_original_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'linkage_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MarketLinkageSubmission::class, 'market_linkage_submission_id');
    }

    public function hasDocument(): bool
    {
        return is_string($this->document_path) && $this->document_path !== '';
    }
}
