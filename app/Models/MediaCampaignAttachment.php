<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaCampaignAttachment extends Model
{
    protected $fillable = [
        'media_campaign_entry_id',
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
        return $this->belongsTo(MediaCampaignEntry::class, 'media_campaign_entry_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toItemArray(): array
    {
        return [
            'id' => (int) $this->id,
            'type' => (string) $this->attachment_type,
            'disk' => (string) $this->disk,
            'path' => (string) $this->path,
            'original_name' => (string) ($this->original_name ?? ''),
            'mime' => (string) ($this->mime ?? ''),
            'size_bytes' => (int) $this->size_bytes,
        ];
    }
}
