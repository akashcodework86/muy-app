<?php

namespace App\Services;

/**
 * Mirrors legacy RBI index.php calculateStage() logic (server-side).
 */
class CfaBusinessStageService
{
    /**
     * @return array{stage: string, criteria_matched: string, logic_lines: list<string>}
     */
    public function compute(string $isRegistered, float $turnover): array
    {
        $reg = $isRegistered === 'Yes';
        $logic = ['Business Stage Calculation Details:', '- Enterprise Registered: '.($reg ? 'Yes' : 'No'),
            '- Turnover Last FY: ₹'.number_format($turnover, 2, '.', ','),
        ];
        $stage = 'Seed';

        if (! $reg && $turnover === 0.0) {
            $stage = 'Seed';
            $logic[] = 'Condition Met: Enterprise not registered and turnover is 0 → Stage: Seed';
        } elseif (! $reg && $turnover > 0 && $turnover <= 500_000) {
            $stage = 'Early';
            $logic[] = 'Condition Met: Enterprise not registered and turnover between 1 and 5 Lakh → Stage: Early';
        } elseif ($reg && $turnover > 0 && $turnover <= 500_000) {
            $stage = 'Early';
            $logic[] = 'Condition Met: Enterprise registered and turnover between 1 and 5 Lakh → Stage: Early';
        } elseif ($reg && $turnover > 500_000) {
            $stage = 'Growth';
            $logic[] = 'Condition Met: Enterprise registered and turnover above 5 Lakh → Stage: Growth';
        } else {
            $logic[] = 'No specific condition met. Defaulting to Seed stage.';
        }
        $logic[] = 'Stage Determined: '.$stage;

        $criteria = 'Registered: '.($reg ? 'Yes' : 'No').', Turnover: ₹'.number_format($turnover, 0, '.', ',');

        return [
            'stage' => $stage,
            'criteria_matched' => $criteria,
            'logic_lines' => $logic,
        ];
    }

    public static function parseTurnover(string $raw): float
    {
        $clean = str_replace(',', '', trim($raw));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
