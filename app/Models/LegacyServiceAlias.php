<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LegacyServiceAlias extends Model
{
    protected $fillable = [
        'source_phase',
        'original_name',
        'normalized_name',
        'normalized_hash',
        'service_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
