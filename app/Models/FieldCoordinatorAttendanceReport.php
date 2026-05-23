<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldCoordinatorAttendanceReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const TYPE_FIELD_VISIT = 'field_visit';

    public const TYPE_BLOCK_WORKSHOP = 'block_workshop';

    protected $fillable = [
        'field_coordinator_user_id',
        'field_coordinator_name',
        'visit_date',
        'entry_date',
        'area',
        'block',
        'district_block_id',
        'gram_panchayat_id',
        'remark',
        'visit_media_json',
        'district_id',
        'villages_visited_total',
        'villages_covered',
        'participants_total',
        'participants_male_count',
        'participants_female_count',
        'cfas_filled_total',
        'outreach_programmes_total',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
        'attendance_sheet_path',
        'attendance_sheet_original_name',
        'attendance_sheet_mime',
        'attendance_sheet_size_bytes',
        'status',
        'record_type',
        'participants_json',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'entry_date' => 'date',
            'villages_covered' => 'array',
            'visit_media_json' => 'array',
            'participants_json' => 'array',
        ];
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        if (! self::supportsDraftWorkflow()) {
            return $query;
        }

        return $query->where(function (Builder $q): void {
            $q->where('status', self::STATUS_SUBMITTED)
                ->orWhereNull('status');
        });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeBlockWorkshops(Builder $query): Builder
    {
        if (! self::supportsRecordType()) {
            return $query;
        }

        return $query->where('record_type', self::TYPE_BLOCK_WORKSHOP);
    }

    public function scopeFieldVisits(Builder $query): Builder
    {
        if (! self::supportsRecordType()) {
            return $query;
        }

        return $query->where('record_type', self::TYPE_FIELD_VISIT);
    }

    public static function supportsRecordType(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn(
            (new self)->getTable(),
            'record_type',
        );
    }

    public static function supportsDraftWorkflow(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn(
            (new self)->getTable(),
            'status',
        );
    }

    public function isDraft(): bool
    {
        return self::supportsDraftWorkflow()
            && (string) ($this->status ?? self::STATUS_SUBMITTED) === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return ! $this->isDraft();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function participantRows(): array
    {
        $rows = $this->participants_json;

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Participant count grouped by gram panchayat (from participant rows).
     *
     * @return list<array{name: string, count: int}>
     */
    public function participantCountsByGramPanchayat(): array
    {
        $rows = $this->participantRows();
        $defaultGpName = $this->relationLoaded('gramPanchayat')
            ? trim((string) ($this->gramPanchayat?->name ?? ''))
            : '';

        if ($rows === []) {
            if ($defaultGpName !== '' && (int) $this->participants_total > 0) {
                return [
                    ['name' => $defaultGpName, 'count' => (int) $this->participants_total],
                ];
            }

            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['gram_panchayat_name'] ?? ''));
            if ($name === '') {
                $name = $defaultGpName !== '' ? $defaultGpName : 'Not specified';
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $result = [];
        foreach ($counts as $name => $count) {
            $result[] = ['name' => $name, 'count' => $count];
        }

        usort($result, static function (array $a, array $b): int {
            $byCount = $b['count'] <=> $a['count'];
            if ($byCount !== 0) {
                return $byCount;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_coordinator_user_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function districtBlock(): BelongsTo
    {
        return $this->belongsTo(DistrictBlock::class);
    }

    public function gramPanchayat(): BelongsTo
    {
        return $this->belongsTo(GramPanchayat::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visitMediaItems(): array
    {
        return collect((array) $this->visit_media_json)
            ->filter(fn ($item) => is_array($item) && (string) ($item['path'] ?? '') !== '')
            ->values()
            ->all();
    }

    public function hasAttendanceSheet(): bool
    {
        return (string) ($this->attendance_sheet_path ?? '') !== '';
    }

    /**
     * Label used in attendance sheet template column G (matches legacy rows using area/village).
     */
    public function attendanceSheetGramPanchayatLabel(): string
    {
        $name = trim((string) ($this->gramPanchayat?->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($this->area ?? ''));
    }

    public function isLegacyNumericReport(): bool
    {
        return $this->visit_media_json === null
            && (
                (int) $this->villages_visited_total > 0
                || (int) $this->participants_total > 0
                || (int) $this->cfas_filled_total > 0
                || (int) $this->outreach_programmes_total > 0
                || $this->attachment_path !== null
            );
    }
}
