<?php

namespace App\Services\Deliverables;

class ProgramDeliverablesMatrix
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findLeafBySerial(string $serial): ?array
    {
        $serial = trim($serial);
        if ($serial === '') {
            return null;
        }

        foreach (config('program_deliverables.matrix', []) as $pillarIndex => $pillar) {
            $found = self::walkNode($pillar, [(string) ($pillarIndex + 1)], $serial);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $serialParts
     * @return array<string, mixed>|null
     */
    private static function walkNode(array $node, array $serialParts, string $targetSerial): ?array
    {
        $serial = implode('.', $serialParts);
        $rowType = (string) ($node['row_type'] ?? 'leaf');

        if ($serial === $targetSerial && $rowType === 'leaf' && isset($node['source'])) {
            return $node;
        }

        foreach ($node['children'] ?? [] as $childIndex => $child) {
            $found = self::walkNode($child, [...$serialParts, (string) ($childIndex + 1)], $targetSerial);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
