<?php

namespace Tests\Unit;

use App\Models\District;
use App\Models\User;
use App\Support\AccelerationServicesAccess;
use PHPUnit\Framework\TestCase;

class AccelerationServicesAccessTest extends TestCase
{
    public function test_district_staff_can_select_only_onboarded_applicant_from_own_district(): void
    {
        $user = new User(['role' => 'district_staff']);
        $user->setRelation('district', new District(['name' => 'Haridwar']));

        $eligible = [
            'onboard_label' => 'Onboarded',
            'district_name' => 'Haridwar',
        ];
        $otherDistrict = [
            'onboard_label' => 'Onboarded',
            'district_name' => 'Dehradun',
        ];
        $notOnboarded = [
            'onboard_label' => 'Non onboarded',
            'district_name' => 'Haridwar',
        ];

        $this->assertNull(AccelerationServicesAccess::applicantEligibilityError($user, $eligible));
        $this->assertSame(
            'You can select only onboarded Phase 1 incubatees from your assigned district.',
            AccelerationServicesAccess::applicantEligibilityError($user, $otherDistrict),
        );
        $this->assertSame(
            'Only onboarded Phase 1 incubatees can be selected.',
            AccelerationServicesAccess::applicantEligibilityError($user, $notOnboarded),
        );
    }

    public function test_state_staff_can_select_onboarded_applicant_from_any_district(): void
    {
        $user = new User(['role' => 'state_staff']);

        $this->assertNull(AccelerationServicesAccess::applicantEligibilityError($user, [
            'onboard_label' => 'Onboarded',
            'district_name' => 'Pithoragarh',
        ]));
    }
}
