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
        'male_participants',
        'female_participants',
        'attendance_media_json',
        'workshop_photos_json',
        'selected_incubatee_ids',
        'selected_incubatees_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'male_participants' => 'integer',
            'female_participants' => 'integer',
            'attendance_media_json' => 'array',
            'workshop_photos_json' => 'array',
            'selected_incubatee_ids' => 'array',
            'selected_incubatees_snapshot' => 'array',
        ];
    }

    public function totalParticipantCount(): int
    {
        $fromCounts = (int) ($this->male_participants ?? 0) + (int) ($this->female_participants ?? 0);
        if ($fromCounts > 0) {
            return $fromCounts;
        }

        if (is_array($this->selected_incubatee_ids) && count($this->selected_incubatee_ids) > 0) {
            return count($this->selected_incubatee_ids);
        }

        return count((array) $this->selected_incubatees_snapshot);
    }

    public function hasAttendanceSheet(): bool
    {
        return count((array) $this->attendance_media_json) > 0;
    }

    public function isAttendancePending(): bool
    {
        return ! $this->hasAttendanceSheet();
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
