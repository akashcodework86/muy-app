<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DemoDay extends Model
{
    public const MODULE_LABEL = '8.4 Demo Days';

    protected $fillable = [
        'event_date',
        'event_name',
        'event_type',
        'investor_name',
        'event_type_other',
        'venue',
        'mode',
        'male_participants',
        'female_participants',
        'cfa_submission_id',
        'legacy_application_id',
        'district_id',
        'incubatee_name',
        'application_no',
        'participating_incubatees_json',
        'outcome',
        'summary',
        'remarks',
        'proof_file_disk',
        'proof_file_path',
        'proof_file_name',
        'event_photos_json',
        'entered_by_user_id',
        'entered_by_name',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'male_participants' => 'integer',
            'female_participants' => 'integer',
            'participating_incubatees_json' => 'array',
            'event_photos_json' => 'array',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eventPhotoItems(): array
    {
        $json = $this->event_photos_json;
        if (is_array($json) && $json !== []) {
            return array_values($json);
        }

        if ($this->hasLegacyProofFile()) {
            return [[
                'path' => (string) $this->proof_file_path,
                'disk' => (string) ($this->proof_file_disk ?: config('filesystems.default')),
                'original_name' => (string) ($this->proof_file_name ?: 'event-proof'),
                'mime' => '',
                'type' => 'image',
            ]];
        }

        return [];
    }

    public function hasEventPhotos(): bool
    {
        return $this->eventPhotoItems() !== [];
    }

    public function hasLegacyProofFile(): bool
    {
        return is_string($this->proof_file_path) && $this->proof_file_path !== '';
    }

    public function hasProofFile(): bool
    {
        return $this->hasEventPhotos();
    }

    public function deleteStoredEventPhotos(): void
    {
        foreach ($this->eventPhotoItems() as $item) {
            if (! is_array($item)) {
                continue;
            }

            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $disk = (string) ($item['disk'] ?? config('filesystems.default'));
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function participatingIncubatees(): array
    {
        $json = $this->participating_incubatees_json;
        if (is_array($json) && $json !== []) {
            return array_values($json);
        }

        if (trim((string) ($this->incubatee_name ?? '')) === '') {
            return [];
        }

        return [[
            'key' => $this->incubateeKeyFromIds(
                (int) ($this->cfa_submission_id ?? 0),
                (int) ($this->legacy_application_id ?? 0),
            ),
            'cfa_submission_id' => $this->cfa_submission_id ? (int) $this->cfa_submission_id : null,
            'legacy_application_id' => $this->legacy_application_id ? (int) $this->legacy_application_id : null,
            'name' => (string) $this->incubatee_name,
            'application_no' => $this->application_no,
            'district_id' => $this->district_id ? (int) $this->district_id : null,
        ]];
    }

    public function incubateeNamesSummary(): string
    {
        $names = array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $this->participatingIncubatees(),
        )));

        return implode('; ', $names);
    }

    public function participatingIncubateeCount(): int
    {
        return count($this->participatingIncubatees());
    }

    private function incubateeKeyFromIds(int $cfaId, int $legacyId): string
    {
        if ($cfaId > 0) {
            return 'cfa:'.$cfaId;
        }

        if ($legacyId > 0) {
            return 'legacy:'.$legacyId;
        }

        return '';
    }

    protected static function booted(): void
    {
        static::deleting(function (DemoDay $row): void {
            $row->deleteStoredEventPhotos();
        });
    }

    public function deleteStoredProofFile(): void
    {
        $this->deleteStoredEventPhotos();
    }

    public function totalParticipants(): int
    {
        $counts = \App\Support\IncubateeAttendeeCounts::fromSnapshots($this->participatingIncubatees());
        if ($counts['total'] > 0) {
            return $counts['total'];
        }

        return (int) $this->male_participants + (int) $this->female_participants;
    }

    /**
     * @return array{male: int, female: int, total: int}
     */
    public function participantCounts(): array
    {
        $counts = \App\Support\IncubateeAttendeeCounts::fromSnapshots($this->participatingIncubatees());
        if ($counts['total'] > 0) {
            return $counts;
        }

        $male = (int) $this->male_participants;
        $female = (int) $this->female_participants;

        return [
            'male' => $male,
            'female' => $female,
            'total' => max($male + $female, $this->participatingIncubateeCount()),
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }
}
