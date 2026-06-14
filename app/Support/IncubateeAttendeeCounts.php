<?php

namespace App\Support;

use App\Models\TechnicalTraining;
use App\Models\TrainingPackage;

final class IncubateeAttendeeCounts
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @return array{male: int, female: int, total: int}
     */
    public static function fromSnapshots(array $snapshots): array
    {
        $items = array_values(array_filter($snapshots, static fn ($item): bool => is_array($item)));

        $male = 0;
        $female = 0;
        foreach ($items as $snap) {
            if (self::isFemaleGender((string) ($snap['gender'] ?? ''))) {
                $female++;
            } elseif (self::isMaleGender((string) ($snap['gender'] ?? ''))) {
                $male++;
            }
        }

        return [
            'male' => $male,
            'female' => $female,
            'total' => count($items),
        ];
    }

    /**
     * @return array{male: int, female: int, total: int}
     */
    public static function fromTrainingRecord(TechnicalTraining|TrainingPackage $row): array
    {
        $ids = is_array($row->selected_incubatee_ids) ? $row->selected_incubatee_ids : [];
        $snapshots = array_values(array_filter(
            (array) ($row->selected_incubatees_snapshot ?? []),
            static fn ($item): bool => is_array($item),
        ));

        $male = 0;
        $female = 0;
        foreach ($snapshots as $snap) {
            if (self::isFemaleGender((string) ($snap['gender'] ?? ''))) {
                $female++;
            } elseif (self::isMaleGender((string) ($snap['gender'] ?? ''))) {
                $male++;
            }
        }

        $total = count($ids) > 0 ? count($ids) : count($snapshots);

        return [
            'male' => $male,
            'female' => $female,
            'total' => $total,
        ];
    }

    /**
     * @param  iterable<TechnicalTraining|TrainingPackage>  $records
     * @return array{male: int, female: int, total: int}
     */
    public static function sumForRecords(iterable $records): array
    {
        $male = 0;
        $female = 0;
        $total = 0;

        foreach ($records as $row) {
            if (! $row instanceof TechnicalTraining && ! $row instanceof TrainingPackage) {
                continue;
            }
            $counts = self::fromTrainingRecord($row);
            $male += $counts['male'];
            $female += $counts['female'];
            $total += $counts['total'];
        }

        return [
            'male' => $male,
            'female' => $female,
            'total' => $total,
        ];
    }

    private static function isMaleGender(string $gender): bool
    {
        $normalized = strtolower(trim($gender));

        return in_array($normalized, ['male', 'm', 'man'], true);
    }

    private static function isFemaleGender(string $gender): bool
    {
        $normalized = strtolower(trim($gender));

        return in_array($normalized, ['female', 'f', 'woman'], true);
    }
}
