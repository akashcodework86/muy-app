<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCampaignEntry extends Model
{
    public const MODULE_LABEL = 'Newspaper & Radio campaigns (MIS 10.4)';

    protected $fillable = [
        'campaign_date',
        'media_type',
        'channel_name',
        'coverage_area',
        'campaign_title',
        'ad_size_or_duration',
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
            'campaign_date' => 'date',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaCampaignAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hasDocument(): bool
    {
        return is_string($this->document_path) && $this->document_path !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function multimediaItems(): array
    {
        return $this->attachments
            ->map(fn (MediaCampaignAttachment $row) => $row->toItemArray())
            ->values()
            ->all();
    }
}
