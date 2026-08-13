<?php

namespace Tests\Unit;

use App\Services\LegacyData\LegacyServiceNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyServiceNameNormalizerTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider approvedMappings */
    public function test_client_approved_historical_service_mappings(string $source, string $expected): void
    {
        $result = app(LegacyServiceNameNormalizer::class)->resolve($source, 'Phase 2');

        $this->assertTrue($result['mapped']);
        $this->assertSame('approved_mapping', $result['mapping_source']);
        $this->assertSame($expected, $result['label']);
    }

    public static function approvedMappings(): array
    {
        return [
            ['Support in Application process', 'Schematic Convergence'],
            ['Loan / scheme', 'Schematic Convergence'],
            ['Other Licensing Support', 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)'],
            ['Shop & Establishment', 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)'],
            ['IPR support', 'Advance Licensing Support (Mandi Licensing, Lab Test etc.)'],
            ['Product Diversification', 'Identification and Submission of Proposal for New Product Development'],
            ['Unit setup', 'Initiation of acceleration and co-incubation services'],
            ['Support in business', 'Others'],
            ['Photoshoot', 'Other Support Services - Labelling, Packaging, Logo Designing etc.'],
            ['Other Support Service', 'Other Support Services - Labelling, Packaging, Logo Designing etc.'],
            ['Mentorship', 'Specialized Mentorship Support'],
            ['Training Package 4', 'Incubatees taken Part in Business Modules Training'],
            ['Technical training', 'Technical Trainings to Incubatees'],
            ['Trade fair Particiepataion', 'Events/ Seminars/ Workshops'],
        ];
    }

    public function test_business_registration_uses_utdb_detail_when_available(): void
    {
        $normalizer = app(LegacyServiceNameNormalizer::class);

        $this->assertSame(
            'Business Registration',
            $normalizer->resolve('Business registration', 'Phase 1', 'Proprietorship')['label']
        );
        $this->assertSame(
            'UTDB Registration',
            $normalizer->resolve('Business registration', 'Phase 1', 'UTDB Registration')['label']
        );
    }

    public function test_phase3_names_are_not_rewritten_by_historical_rules(): void
    {
        $result = app(LegacyServiceNameNormalizer::class)->resolve('MUDRA', 'Phase 3');

        $this->assertSame('MUDRA', $result['label']);
        $this->assertNotSame('approved_mapping', $result['mapping_source']);
    }
}
