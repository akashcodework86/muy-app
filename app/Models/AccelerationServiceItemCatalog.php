<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccelerationServiceItemCatalog extends Model
{
    protected $table = 'acceleration_service_item_catalog';

    protected $fillable = [
        'section',
        'item_key',
        'item_label',
        'is_system',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
