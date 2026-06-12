<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakeholderCapacityBuildingSession extends Model
{
    public const MODULE_LABEL = '3.4 Capacity building of stakeholders';

    /** @var array<string, string> */
    public const STAKEHOLDER_TYPES = [
        'reap' => 'REAP',
        'usrlm' => 'USRLM',
        'line_department' => 'Line department',
        'other' => 'Other',
    ];

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'session_date',
        'workshop_mode',
        'venue',
        'stakeholder_type',
        'stakeholder_type_other',
        'department_name',
        'session_title',
        'topics_covered',
        'staff_trained_total',
        'attendance_media_json',
        'workshop_photos_json',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'staff_trained_total' => 'integer',
            'attendance_media_json' => 'array',
            'workshop_photos_json' => 'array',
        ];
    }

    public function stakeholderTypeLabel(): string
    {
        $key = strtolower(trim((string) ($this->stakeholder_type ?? '')));

        if ($key === 'other') {
            $other = trim((string) ($this->stakeholder_type_other ?? ''));

            return $other !== '' ? 'Other: '.$other : 'Other';
        }

        return self::STAKEHOLDER_TYPES[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public function formattedWorkshopMode(): string
    {
        return match (strtolower(trim((string) ($this->workshop_mode ?? '')))) {
            'virtual' => 'Virtual',
            'physical' => 'Physical',
            default => 'Physical',
        };
    }

    public function hasAttendanceSheet(): bool
    {
        return count((array) $this->attendance_media_json) > 0;
    }

    public function hasWorkshopPhotos(): bool
    {
        return $this->workshopPhotoCount() > 0;
    }

    public function workshopPhotoCount(): int
    {
        return count(array_filter(
            (array) $this->workshop_photos_json,
            fn ($item): bool => is_array($item) && (string) ($item['path'] ?? '') !== '',
        ));
    }

    /** @return list<array<string, mixed>> */
    public function workshopPhotoItems(): array
    {
        return array_values(array_filter(
            (array) $this->workshop_photos_json,
            fn ($item): bool => is_array($item) && (string) ($item['path'] ?? '') !== '',
        ));
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
