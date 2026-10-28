<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSellerMeetPhoto extends Model
{
    protected $fillable = [
        'buyer_seller_meet_entry_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'sort_order',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BuyerSellerMeetEntry::class, 'buyer_seller_meet_entry_id');
    }
}
