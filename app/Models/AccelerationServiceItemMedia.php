<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccelerationServiceItemMedia extends Model
{
    protected $table = 'acceleration_service_item_media';

    protected $fillable = [
        'item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AccelerationServiceItem::class, 'item_id');
    }
}
