<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProgramDeliverable104DefinitionTest extends TestCase
{
    public function test_indicator_10_4_has_the_requested_name_and_is_non_key(): void
    {
        $matrixRow = $this->matrixNodeForSerial('10.4');
        $officialRow = collect(config('official_state_monthly_targets.rows', []))
            ->firstWhere('serial', '10.4');
        $stateIndicator = collect(config('program_deliverables.state_monthly_indicators', []))
            ->firstWhere('serial', '10.4');

        $this->assertSame('IEC & Promotional Activities for MUY', $matrixRow['name'] ?? null);
        $this->assertSame('Non-Key', $matrixRow['indicator_type'] ?? null);
        $this->assertSame('IEC & Promotional Activities for MUY', $officialRow['name'] ?? null);
        $this->assertSame('Non-Key', $officialRow['indicator_type'] ?? null);
        $this->assertSame('IEC & Promotional Activities for MUY', $stateIndicator['name'] ?? null);
    }

    /** @return array<string, mixed> */
    private function matrixNodeForSerial(string $targetSerial): array
    {
        $pillarIndex = 0;

        foreach (config('program_deliverables.matrix', []) as $pillar) {
            $pillarIndex++;
            $found = $this->findNode($pillar, [(string) $pillarIndex], $targetSerial);

            if ($found !== null) {
                return $found;
            }
        }

        $this->fail('Missing matrix node for serial '.$targetSerial);
    }

    /**
     * @param  list<string>  $serialParts
     * @return array<string, mixed>|null
     */
    private function findNode(array $node, array $serialParts, string $targetSerial): ?array
    {
        if (implode('.', $serialParts) === $targetSerial) {
            return $node;
        }

        $childIndex = 0;
        foreach ($node['children'] ?? [] as $child) {
            $childIndex++;
            $found = $this->findNode($child, [...$serialParts, (string) $childIndex], $targetSerial);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
