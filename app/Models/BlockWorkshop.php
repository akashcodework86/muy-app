<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockWorkshop extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'field_coordinator_user_id',
        'field_coordinator_name',
        'visit_date',
        'entry_date',
        'district_id',
        'district_block_id',
        'gram_panchayat_id',
        'block',
        'area',
        'remark',
        'participants_male_count',
        'participants_female_count',
        'participants_total',
        'participants_json',
        'visit_media_json',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
        'attendance_sheet_path',
        'attendance_sheet_original_name',
        'attendance_sheet_mime',
        'attendance_sheet_size_bytes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'entry_date' => 'date',
            'visit_media_json' => 'array',
            'participants_json' => 'array',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', self::STATUS_SUBMITTED)
                ->orWhereNull('status');
        });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return (string) ($this->status ?? self::STATUS_SUBMITTED) === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return ! $this->isDraft();
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function visitMediaItems(): array
    {
        return collect((array) $this->visit_media_json)
            ->filter(fn ($item) => is_array($item) && (string) ($item['path'] ?? '') !== '')
            ->values()
            ->all();
    }

    // ── Participants ──────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
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
                return [['name' => $defaultGpName, 'count' => (int) $this->participants_total]];
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

            return $byCount !== 0 ? $byCount : strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    // ── Attendance sheet ──────────────────────────────────────────────────────

    public function hasAttendanceSheet(): bool
    {
        return (string) ($this->attendance_sheet_path ?? '') !== '';
    }

    public function attendanceSheetGramPanchayatLabel(): string
    {
        $name = trim((string) ($this->gramPanchayat?->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($this->area ?? ''));
    }

    // ── Relationships ─────────────────────────────────────────────────────────

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
}
