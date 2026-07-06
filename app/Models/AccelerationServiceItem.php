<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccelerationServiceItem extends Model
{
    protected $fillable = [
        'session_id',
        'section',
        'item_key',
        'item_label',
        'remarks',
        'is_custom',
        'is_buyer_seller_meet',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'is_buyer_seller_meet' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AccelerationServiceSession::class, 'session_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(AccelerationServiceItemMedia::class, 'item_id');
    }
}
