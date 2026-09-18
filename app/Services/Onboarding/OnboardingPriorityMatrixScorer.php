<?php

namespace App\Services\Onboarding;

use App\Services\CfaBusinessStageService;

/**
 * PMC Priority Matrix (2 Aug 2025) — auto score from CFA payload.
 * Within-stage comparison: callers rank applicants with the same stage key.
 */
final class OnboardingPriorityMatrixScorer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     stage: string,
     *     total: float,
     *     dimensions: array<string, array{label: string, points: float, max: float}>,
     *     highlights: list<string>
     * }
     */
    public function score(array $payload): array
    {
        $stage = $this->stageKey($payload);

        $dimensions = [
            'scalability' => $this->dimension('Scalability', 30.0, $this->scalabilityPoints($payload, $stage)),
            'economic' => $this->dimension('Economic impact', 30.0, $this->economicPoints($payload, $stage)),
            'social' => $this->dimension('Social impact', 20.0, $this->socialPoints($payload, $stage)),
            'vision' => $this->dimension('Vision & clarity', 10.0, $this->visionPoints($payload, $stage)),
            'innovation' => $this->dimension('Innovation & technology', 5.0, $this->innovationPoints($payload, $stage)),
            'environmental' => $this->dimension('Environmental impact', 5.0, $this->environmentalPoints($payload, $stage)),
        ];

        $total = round(array_sum(array_map(static fn (array $d): float => $d['points'], $dimensions)), 1);

        return [
            'stage' => $stage,
            'total' => $total,
            'dimensions' => $dimensions,
            'highlights' => $this->highlights($payload, $stage),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function stageKey(array $payload): string
    {
        $raw = strtolower(trim((string) ($payload['form_stage'] ?? $payload['stage'] ?? '')));
        if (in_array($raw, ['seed', 'early', 'growth'], true)) {
            return $raw;
        }

        $isRegistered = (string) ($payload['is_registered'] ?? '') === 'Yes';
        $turnover = CfaBusinessStageService::parseTurnover((string) ($payload['turnover_last_fy'] ?? '0'));

        return strtolower(CfaBusinessStageService::compute(
            $isRegistered ? 'Yes' : 'No',
            $turnover,
        )['stage']);
    }

    /**
     * @return array{label: string, points: float, max: float}
     */
    private function dimension(string $label, float $max, float $points): array
    {
        return [
            'label' => $label,
            'points' => round(min($max, max(0.0, $points)), 1),
            'max' => $max,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scalabilityPoints(array $payload, string $stage): float
    {
        $points = 0.0;
        $points += $this->weighted($stage === 'seed', 0.0, 5.0, $this->marksRegistered($payload, $stage), 5.0);
        $points += $this->weighted($stage === 'seed', 0.0, 10.0, $this->marksBusinessAge($payload, $stage), 10.0);
        $points += $this->weighted($stage === 'seed', 0.0, 5.0, $this->marksBankLoan($payload, $stage), 5.0);
        $points += $this->weighted($stage === 'seed', 0.0, 5.0, $this->marksMarketingPartners($payload, $stage), 5.0);
        $points += $this->weighted(false, 0.0, 5.0, $this->marksYesNo((string) ($payload['training_received'] ?? '')), 5.0);

        return $points;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function economicPoints(array $payload, string $stage): float
    {
        $points = 0.0;
        $points += $this->weighted($stage === 'seed', 0.0, 10.0, $this->marksEmployment($payload, $stage), 10.0);
        $points += $this->weighted($stage === 'seed', 0.0, 10.0, $this->marksTurnover($payload, $stage), 10.0);
        $points += $this->weighted(false, 0.0, 10.0, $this->marksYesNo((string) ($payload['financial_support'] ?? '')), 10.0);

        return $points;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function socialPoints(array $payload, string $stage): float
    {
        unset($stage);
        $points = 0.0;
        $points += $this->weighted(false, 0.0, 10.0, $this->marksYesNo((string) ($payload['migrated_for_employment'] ?? '')), 10.0);
        $points += $this->weighted(false, 0.0, 10.0, $this->marksYesNo((string) ($payload['empwomen'] ?? '')), 10.0);

        return $points;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function visionPoints(array $payload, string $stage): float
    {
        unset($stage);

        return $this->weighted(false, 0.0, 10.0, $this->marksExpectations($payload), 10.0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function innovationPoints(array $payload, string $stage): float
    {
        unset($stage);

        return $this->weighted(false, 0.0, 5.0, $this->marksTechUse((string) ($payload['techuse'] ?? '')), 5.0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function environmentalPoints(array $payload, string $stage): float
    {
        unset($stage);

        return $this->weighted(false, 0.0, 5.0, $this->marksYesNo((string) ($payload['sustainability'] ?? '')), 5.0);
    }

    private function weighted(bool $naForSeed, float $ignored, float $weightPct, float $marks, float $weight): float
    {
        unset($ignored);

        if ($naForSeed) {
            return $weightPct;
        }

        return ($marks / 10.0) * $weight;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksRegistered(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        return $this->marksYesNo((string) ($payload['is_registered'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksBusinessAge(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        $age = trim((string) ($payload['business_age'] ?? ''));

        return match ($age) {
            '0' => 2.0,
            '1-6 months' => 3.0,
            '7-12 months' => 5.0,
            '12-24 months' => 7.0,
            '>24 months' => 10.0,
            default => 2.0,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksBankLoan(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        if ((string) ($payload['loan_taken'] ?? '') !== 'Yes') {
            return 2.0;
        }

        $amount = CfaBusinessStageService::parseTurnover((string) ($payload['bank_loan'] ?? '0'));

        return match (true) {
            $amount <= 0 => 2.0,
            $amount < 100_000 => 5.0,
            $amount <= 500_000 => 7.0,
            default => 10.0,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksMarketingPartners(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        $count = $this->marketingPartnerCount($payload);

        return match (true) {
            $count === 0 => 2.0,
            $count === 1 => 5.0,
            $count <= 4 => 7.0,
            default => 10.0,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marketingPartnerCount(array $payload): int
    {
        if ((string) ($payload['regular_buyer'] ?? '') !== 'Yes') {
            return 0;
        }

        return max(0, (int) ($payload['buyer_count'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksEmployment(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        if ((string) ($payload['current_employment'] ?? '') !== 'Yes') {
            return 2.0;
        }

        $count = max(0, (int) ($payload['employed_count'] ?? 0));

        return match (true) {
            $count === 0 => 2.0,
            $count === 1 => 4.0,
            $count <= 4 => 7.0,
            default => 10.0,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksTurnover(array $payload, string $stage): float
    {
        if ($stage === 'seed') {
            return 10.0;
        }

        $raw = trim((string) ($payload['turnover_last_fy'] ?? ''));
        if ($raw === '') {
            return 2.0;
        }

        $amount = CfaBusinessStageService::parseTurnover($raw);

        return match (true) {
            $amount <= 0 => 2.0,
            $amount < 100_000 => 4.0,
            $amount <= 500_000 => 6.0,
            default => 8.0,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marksExpectations(array $payload): float
    {
        $expectations = $payload['expectations'] ?? [];
        $count = is_array($expectations) ? count($expectations) : 0;

        return match (true) {
            $count <= 2 => 2.0,
            $count <= 5 => 6.0,
            default => 10.0,
        };
    }

    private function marksTechUse(string $tech): float
    {
        $normalized = strtolower(trim($tech));

        return match (true) {
            str_contains($normalized, 'website') => 10.0,
            str_contains($normalized, 'commerce') => 7.0,
            str_contains($normalized, 'social') => 5.0,
            str_contains($normalized, 'whatsapp') => 2.0,
            default => 2.0,
        };
    }

    private function marksYesNo(string $value, float $yes = 10.0, float $no = 5.0): float
    {
        return $value === 'Yes' ? $yes : $no;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function highlights(array $payload, string $stage): array
    {
        $items = [
            ['Entrepreneurship training', $this->marksYesNo((string) ($payload['training_received'] ?? ''))],
            ['Financial support need', $this->marksYesNo((string) ($payload['financial_support'] ?? ''))],
            ['Migration for employment', $this->marksYesNo((string) ($payload['migrated_for_employment'] ?? ''))],
            ['Social empowerment', $this->marksYesNo((string) ($payload['empwomen'] ?? ''))],
            ['MUY expectations depth', $this->marksExpectations($payload)],
            ['Technology use', $this->marksTechUse((string) ($payload['techuse'] ?? ''))],
            ['Environmental sustainability', $this->marksYesNo((string) ($payload['sustainability'] ?? ''))],
        ];

        if ($stage !== 'seed') {
            $items[] = ['Marketing partners', $this->marksMarketingPartners($payload, $stage)];
            $items[] = ['Current employment', $this->marksEmployment($payload, $stage)];
            $items[] = ['Turnover (last FY)', $this->marksTurnover($payload, $stage)];
        }

        usort($items, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_values(array_map(
            static fn (array $row): string => $row[0],
            array_slice($items, 0, 3),
        ));
    }
}
