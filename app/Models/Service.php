<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'service_category_id',
        'deliverable_id',
        'code',
        'name',
        'sort_order',
        'is_active',
        'allows_multiple',
        'field_schema',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_multiple' => 'boolean',
            'field_schema' => 'array',
        ];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ServiceCase::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }
}
