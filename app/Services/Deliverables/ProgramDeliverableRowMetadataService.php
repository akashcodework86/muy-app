<?php

namespace App\Services\Deliverables;

use App\Models\ProgramDeliverableRowMetadata;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * DB overrides for Deliverables report "Type of Indicator" and "Spoke/Hub/State" columns.
 */
final class ProgramDeliverableRowMetadataService
{
    /** @var list<string> */
    public const INDICATOR_TYPES = [
        'Key Indicator',
        'Non-Key',
    ];

    /** @var list<string> */
    public const LEVELS = [
        'Spoke & Hub',
        'State',
        'State, Hub and Spokes',
    ];

    /** @var array<string, array{indicator_type: ?string, level: ?string}>|null */
    private static ?array $cachedBySerial = null;

    /**
     * @return array<string, array{indicator_type: ?string, level: ?string}>
     */
    public function allKeyedBySerial(): array
    {
        if (self::$cachedBySerial !== null) {
            return self::$cachedBySerial;
        }

        if (! Schema::hasTable('program_deliverable_row_metadata')) {
            self::$cachedBySerial = [];

            return self::$cachedBySerial;
        }

        self::$cachedBySerial = ProgramDeliverableRowMetadata::query()
            ->get(['serial', 'indicator_type', 'level'])
            ->mapWithKeys(fn (ProgramDeliverableRowMetadata $row) => [
                $row->serial => [
                    'indicator_type' => filled($row->indicator_type) ? (string) $row->indicator_type : null,
                    'level' => filled($row->level) ? (string) $row->level : null,
                ],
            ])
            ->all();

        return self::$cachedBySerial;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolveIndicatorType(array $node, string $serial): string
    {
        $override = $this->allKeyedBySerial()[$serial]['indicator_type'] ?? null;
        if ($override !== null) {
            return $override;
        }

        return ProgramDeliverableReportingTier::indicatorTypeLabel($node);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolveLevel(array $node, string $serial): string
    {
        $override = $this->allKeyedBySerial()[$serial]['level'] ?? null;
        if ($override !== null) {
            return $override;
        }

        return (string) ($node['level'] ?? '');
    }

    /**
     * @return array{serial: string, indicator_type: string, level: string}
     */
    public function upsertField(string $serial, string $field, ?string $value, User $user): array
    {
        if (! $this->isEditableLeafSerial($serial)) {
            throw new InvalidArgumentException('Invalid indicator serial.');
        }

        if ($field === 'indicator_type') {
            $this->assertAllowedIndicatorType($value);
        } elseif ($field === 'level') {
            $this->assertAllowedLevel($value);
        } else {
            throw new InvalidArgumentException('Invalid metadata field.');
        }

        $normalized = filled($value) ? trim($value) : null;

        $row = ProgramDeliverableRowMetadata::query()->firstOrNew(['serial' => $serial]);
        $row->{$field} = $normalized;
        $row->updated_by_user_id = $user->id;
        $row->save();

        if ($row->indicator_type === null && $row->level === null) {
            $row->delete();
        }

        self::$cachedBySerial = null;

        $node = $this->matrixNodeForSerial($serial);
        $fresh = ProgramDeliverableRowMetadata::query()->where('serial', $serial)->first();

        return [
            'serial' => $serial,
            'indicator_type' => $fresh && filled($fresh->indicator_type)
                ? (string) $fresh->indicator_type
                : ($node ? ProgramDeliverableReportingTier::indicatorTypeLabel($node) : ''),
            'level' => $fresh && filled($fresh->level)
                ? (string) $fresh->level
                : ($node ? (string) ($node['level'] ?? '') : ''),
        ];
    }

    public function isEditableLeafSerial(string $serial): bool
    {
        return in_array($serial, $this->editableLeafSerials(), true);
    }

    /**
     * @return list<string>
     */
    public function editableLeafSerials(): array
    {
        static $serials = null;
        if ($serials !== null) {
            return $serials;
        }

        $serials = [];
        $pillarIndex = 0;
        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $this->collectLeafSerials($pillar, [(string) $pillarIndex], $serials);
        }

        return $serials;
    }

    /**
     * @param  list<string>  $serialParts
     * @param  list<string>  $out
     */
    private function collectLeafSerials(array $node, array $serialParts, array &$out): void
    {
        $rowType = (string) ($node['row_type'] ?? 'leaf');
        $serial = implode('.', $serialParts);

        if ($rowType === 'leaf' && isset($node['source'])) {
            $out[] = $serial;
        }

        $childIndex = 0;
        foreach ($node['children'] ?? [] as $child) {
            $childIndex++;
            $this->collectLeafSerials($child, [...$serialParts, (string) $childIndex], $out);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function matrixNodeForSerial(string $serial): ?array
    {
        $pillarIndex = 0;
        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $found = $this->findNodeBySerial($pillar, [(string) $pillarIndex], $serial);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $serialParts
     * @return array<string, mixed>|null
     */
    private function findNodeBySerial(array $node, array $serialParts, string $targetSerial): ?array
    {
        $serial = implode('.', $serialParts);
        if ($serial === $targetSerial) {
            return $node;
        }

        $childIndex = 0;
        foreach ($node['children'] ?? [] as $child) {
            $childIndex++;
            $found = $this->findNodeBySerial($child, [...$serialParts, (string) $childIndex], $targetSerial);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function assertAllowedIndicatorType(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! in_array($value, self::INDICATOR_TYPES, true)) {
            throw new InvalidArgumentException('Invalid indicator type.');
        }
    }

    private function assertAllowedLevel(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! in_array($value, self::LEVELS, true)) {
            throw new InvalidArgumentException('Invalid level.');
        }
    }

    public static function resetCacheForTesting(): void
    {
        self::$cachedBySerial = null;
    }
}
