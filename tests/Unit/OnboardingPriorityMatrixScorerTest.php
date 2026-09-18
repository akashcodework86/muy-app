<?php

namespace Tests\Unit;

use App\Services\Onboarding\OnboardingPriorityMatrixScorer;
use Tests\TestCase;

class OnboardingPriorityMatrixScorerTest extends TestCase
{
    private OnboardingPriorityMatrixScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new OnboardingPriorityMatrixScorer;
    }

    public function test_seed_applicant_gets_full_na_weight_for_scalability_and_economic_blocks(): void
    {
        $result = $this->scorer->score([
            'form_stage' => 'Seed',
            'is_registered' => 'No',
            'turnover_last_fy' => '0',
            'training_received' => 'Yes',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b', 'c', 'd', 'e', 'g'],
            'techuse' => 'Website',
            'sustainability' => 'Yes',
        ]);

        $this->assertSame('seed', $result['stage']);
        $this->assertSame(30.0, $result['dimensions']['scalability']['points']);
        $this->assertSame(30.0, $result['dimensions']['economic']['points']);
        $this->assertSame(100.0, $result['total']);
    }

    public function test_early_applicant_uses_marketing_partner_buyer_count(): void
    {
        $high = $this->scorer->score([
            'form_stage' => 'Early',
            'is_registered' => 'Yes',
            'business_age' => '>24 months',
            'loan_taken' => 'Yes',
            'bank_loan' => '600000',
            'regular_buyer' => 'Yes',
            'buyer_count' => 5,
            'training_received' => 'Yes',
            'current_employment' => 'Yes',
            'employed_count' => 5,
            'turnover_last_fy' => '600000',
            'financial_support' => 'Yes',
            'migrated_for_employment' => 'Yes',
            'empwomen' => 'Yes',
            'expectations' => ['a', 'b', 'c', 'd', 'e', 'g'],
            'techuse' => 'Website',
            'sustainability' => 'Yes',
        ]);

        $low = $this->scorer->score([
            'form_stage' => 'Early',
            'is_registered' => 'No',
            'business_age' => '0',
            'loan_taken' => 'No',
            'regular_buyer' => 'No',
            'training_received' => 'No',
            'current_employment' => 'No',
            'turnover_last_fy' => '',
            'financial_support' => 'No',
            'migrated_for_employment' => 'No',
            'empwomen' => 'No',
            'expectations' => ['a'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'No',
        ]);

        $this->assertGreaterThan($low['total'], $high['total']);
        $this->assertSame('early', $high['stage']);
    }

    public function test_turnover_above_five_lakh_scores_eight_not_ten_in_economic_block(): void
    {
        $result = $this->scorer->score([
            'form_stage' => 'Growth',
            'is_registered' => 'Yes',
            'business_age' => '>24 months',
            'loan_taken' => 'No',
            'regular_buyer' => 'No',
            'training_received' => 'No',
            'current_employment' => 'No',
            'turnover_last_fy' => '700000',
            'financial_support' => 'No',
            'migrated_for_employment' => 'No',
            'empwomen' => 'No',
            'expectations' => ['a'],
            'techuse' => 'WhatsApp',
            'sustainability' => 'No',
        ]);

        $this->assertSame(8.0, $this->economicTurnoverPoints($result));
    }

    /** @param  array<string, mixed>  $result */
    private function economicTurnoverPoints(array $result): float
    {
        $economic = $result['dimensions']['economic']['points'];
        $employment = 2.0 / 10 * 10;
        $financial = 5.0 / 10 * 10;

        return round($economic - $employment - $financial, 1);
    }
}
