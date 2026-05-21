<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EapEdpSession extends Model
{
    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'event_date',
        'district_id',
        'district_name',
        'program_type',
        'topic',
        'venue_name_address',
        'workshop_mode',
        'attendance_male_count',
        'attendance_female_count',
        'attendance_total_count',
        'notes',
        'attendance_media_json',
        'session_photos_json',
        'selected_incubatee_ids',
        'selected_incubatees_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendance_male_count' => 'integer',
            'attendance_female_count' => 'integer',
            'attendance_total_count' => 'integer',
            'attendance_media_json' => 'array',
            'session_photos_json' => 'array',
            'selected_incubatee_ids' => 'array',
            'selected_incubatees_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EapEdpSession $session): void {
            $session->attendance_total_count = (int) ($session->attendance_male_count ?? 0) + (int) ($session->attendance_female_count ?? 0);
        });
    }

    protected function displayVenue(): Attribute
    {
        return Attribute::get(function (): string {
            $venue = trim((string) ($this->venue_name_address ?? ''));
            if ($venue !== '') {
                return $venue;
            }

            return trim((string) ($this->topic ?? ''));
        });
    }

    public function hasAttendanceSheet(): bool
    {
        return count((array) $this->attendance_media_json) > 0;
    }

    public function hasSessionPhotos(): bool
    {
        return count((array) $this->session_photos_json) > 0;
    }

    protected function formattedProgramType(): Attribute
    {
        return Attribute::get(function (): string {
            $raw = strtolower(trim((string) ($this->attributes['program_type'] ?? '')));

            return match ($raw) {
                'eap_edp', 'eap', 'edp' => 'EAP/EDP',
                default => $raw !== '' ? strtoupper($raw) : 'EAP/EDP',
            };
        });
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
