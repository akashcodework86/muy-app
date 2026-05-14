<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictWorkshopSession extends Model
{
    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'event_date',
        'district_id',
        'district_name',
        'topic',
        'workshop_mode',
        'notes',
        'attendance_media_json',
        'selected_incubatee_ids',
        'selected_incubatees_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendance_media_json' => 'array',
            'selected_incubatee_ids' => 'array',
            'selected_incubatees_snapshot' => 'array',
        ];
    }

    protected function formattedProgramType(): Attribute
    {
        return Attribute::get(fn (): string => 'District level workshop');
    }

    protected function formattedWorkshopMode(): Attribute
    {
        return Attribute::get(function (): string {
            return match (strtolower(trim((string) ($this->attributes['workshop_mode'] ?? '')))) {
                'virtual' => 'Virtual workshop',
                'physical' => 'Physical workshop',
                default => 'Physical workshop',
            };
        });
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
