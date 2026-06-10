<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LakhpatiTechnicalTraining extends Model
{
    protected $table = 'potential_lakhpati_technical_trainings';

    public const MODULE_LABEL = '3.3.1 Technical Trainings to Potential Lakhpati Didis/ SHG Members/ CBOs';

    /** @var array<string, string> */
    public const AGENCY_TYPES = [
        'nrlm_usrlm' => 'NRLM / USRLM',
        'reap' => 'REAP',
        'uyrp' => 'UYRP',
        'line_department' => 'Line Department',
        'shg_federation' => 'SHG Federation',
        'cbo_network' => 'CBO Network',
        'other' => 'Other',
    ];

    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'session_date',
        'district_id',
        'district_name',
        'district_block_id',
        'block',
        'gram_panchayat_id',
        'area',
        'workshop_mode',
        'requesting_agency_type',
        'session_title',
        'session_brief',
        'male_participants',
        'female_participants',
        'participants_total',
        'participants_json',
        'attendance_media_json',
        'workshop_photos_json',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'male_participants' => 'integer',
            'female_participants' => 'integer',
            'participants_total' => 'integer',
            'participants_json' => 'array',
            'attendance_media_json' => 'array',
            'workshop_photos_json' => 'array',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function participantRows(): array
    {
        $rows = $this->participants_json;

        return is_array($rows) ? array_values($rows) : [];
    }

    public function agencyTypeLabel(): string
    {
        $key = strtolower(trim((string) ($this->requesting_agency_type ?? '')));

        return self::AGENCY_TYPES[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public function formattedWorkshopMode(): string
    {
        return match (strtolower(trim((string) ($this->workshop_mode ?? '')))) {
            'virtual' => 'Virtual workshop',
            'physical' => 'Physical workshop',
            default => 'Physical workshop',
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

    public function totalParticipantCount(): int
    {
        $fromTotal = (int) ($this->participants_total ?? 0);
        if ($fromTotal > 0) {
            return $fromTotal;
        }

        return (int) ($this->male_participants ?? 0) + (int) ($this->female_participants ?? 0);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function districtBlock(): BelongsTo
    {
        return $this->belongsTo(DistrictBlock::class, 'district_block_id');
    }

    public function gramPanchayat(): BelongsTo
    {
        return $this->belongsTo(GramPanchayat::class, 'gram_panchayat_id');
    }
}
