<?php

namespace Tests\Unit;

use App\Support\ApplicantCategoryShgSupport;
use PHPUnit\Framework\TestCase;

class ApplicantCategoryShgSupportTest extends TestCase
{
    public function test_it_reads_current_applicant_category_and_shg_member_fields(): void
    {
        $payload = ['category' => 'Individual', 'is_member' => 'Yes'];

        $this->assertSame('Individual', ApplicantCategoryShgSupport::categoryLabel($payload));
        $this->assertSame('Yes', ApplicantCategoryShgSupport::shgMemberLabel($payload));
    }

    public function test_it_supports_historical_aliases_and_boolean_values(): void
    {
        $this->assertSame('SHG', ApplicantCategoryShgSupport::categoryLabel(['app_category' => 'self help group']));
        $this->assertSame('No', ApplicantCategoryShgSupport::shgMemberLabel(['is_shg_member' => false]));
        $this->assertSame('Yes', ApplicantCategoryShgSupport::shgMemberLabel(['member_of_shg_cbo' => 1]));
    }

    public function test_it_uses_legacy_fallbacks_only_when_payload_values_are_missing(): void
    {
        $this->assertSame('CBO', ApplicantCategoryShgSupport::categoryLabel([], 'CBO'));
        $this->assertSame('No', ApplicantCategoryShgSupport::shgMemberLabel([], 0));
        $this->assertSame('Yes', ApplicantCategoryShgSupport::shgMemberLabel(['is_member' => 'Yes'], 0));
    }
}
