<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCheckIn extends Model
{
    protected $fillable = [
        'user_id',
        'check_in_date',
        'marked_at',
        'latitude',
        'longitude',
        'accuracy_m',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'marked_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function googleMapsUrl(): string
    {
        return 'https://www.google.com/maps?q='.urlencode((string) $this->latitude.','.(string) $this->longitude);
    }
}
