<?php

namespace App\Support;

use App\Models\TechnicalTraining;
use App\Models\TrainingPackage;

final class IncubateeAttendeeCounts
{
    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @return array{male: int, female: int, other: int, total: int}
     */
    public static function fromSnapshots(array $snapshots): array
    {
        $items = array_values(array_filter($snapshots, static fn ($item): bool => is_array($item)));

        $male = 0;
        $female = 0;
        $other = 0;
        foreach ($items as $snap) {
            if (self::isFemaleGender((string) ($snap['gender'] ?? ''))) {
                $female++;
            } elseif (self::isMaleGender((string) ($snap['gender'] ?? ''))) {
                $male++;
            } else {
                $other++;
            }
        }

        return [
            'male' => $male,
            'female' => $female,
            'other' => $other,
            'total' => count($items),
        ];
    }

    /**
     * @return array{male: int, female: int, other: int, total: int}
     */
    public static function fromTrainingRecord(TechnicalTraining|TrainingPackage $row): array
    {
        $snapshots = array_values(array_filter(
            (array) ($row->selected_incubatees_snapshot ?? []),
            static fn ($item): bool => is_array($item),
        ));

        $male = 0;
        $female = 0;
        $other = 0;
        foreach ($snapshots as $snap) {
            if (self::isFemaleGender((string) ($snap['gender'] ?? ''))) {
                $female++;
            } elseif (self::isMaleGender((string) ($snap['gender'] ?? ''))) {
                $male++;
            } else {
                $other++;
            }
        }

        return [
            'male' => $male,
            'female' => $female,
            'other' => $other,
            'total' => count($snapshots),
        ];
    }

    /**
     * @param  iterable<TechnicalTraining|TrainingPackage>  $records
     * @return array{male: int, female: int, other: int, total: int}
     */
    public static function sumForRecords(iterable $records): array
    {
        $male = 0;
        $female = 0;
        $other = 0;
        $total = 0;

        foreach ($records as $row) {
            if (! $row instanceof TechnicalTraining && ! $row instanceof TrainingPackage) {
                continue;
            }
            $counts = self::fromTrainingRecord($row);
            $male += $counts['male'];
            $female += $counts['female'];
            $other += $counts['other'];
            $total += $counts['total'];
        }

        return [
            'male' => $male,
            'female' => $female,
            'other' => $other,
            'total' => $total,
        ];
    }

    /**
     * Normalize a raw gender string (from CFA payload or legacy DB) to a canonical display value.
     * Returns 'Male', 'Female', or the original trimmed string for Other / NA / unknown values.
     */
    public static function normalizeGender(string $raw): string
    {
        $v = strtolower(trim($raw));

        if (self::isMaleGender($v)) {
            return 'Male';
        }

        if (self::isFemaleGender($v)) {
            return 'Female';
        }

        $trimmed = trim($raw);

        return $trimmed !== '' ? $trimmed : '';
    }

    private static function isMaleGender(string $gender): bool
    {
        $v = strtolower(trim($gender));

        // English variants + Hindi 'पुरुष' (purush) + legacy numeric code '1'
        return in_array($v, ['male', 'm', 'man', 'पुरुष', '1'], true);
    }

    private static function isFemaleGender(string $gender): bool
    {
        $v = strtolower(trim($gender));

        // English variants + Hindi 'महिला' (mahila) / 'स्त्री' (stri) + legacy numeric code '2'
        return in_array($v, ['female', 'f', 'woman', 'महिला', 'स्त्री', '2'], true);
    }
}
