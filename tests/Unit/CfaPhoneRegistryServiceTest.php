<?php

namespace Tests\Unit;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\Hub;
use App\Services\CfaPhoneRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CfaPhoneRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_a_current_mis_duplicate_before_external_checks(): void
    {
        $hub = Hub::query()->create(['slug' => 'registry-hub', 'name' => 'Registry Hub', 'sort_order' => 1]);
        $district = District::query()->create([
            'hub_id' => $hub->id,
            'slug' => 'registry-district',
            'name' => 'Registry District',
            'sort_order' => 1,
        ]);
        CfaSubmission::query()->create([
            'application_no' => 'CURRENT-123',
            'district_id' => $district->id,
            'applicant_name' => 'Existing Applicant',
            'phone' => '9876543210',
            'payload' => [],
        ]);

        $result = app(CfaPhoneRegistryService::class)->inspect('9876543210');

        $this->assertFalse($result['available']);
        $this->assertSame('CURRENT-123', $result['duplicate']['application_no']);
        $this->assertSame('Existing Applicant', $result['duplicate']['name']);
        $this->assertSame([], $result['unavailable_sources']);
    }

    public function test_it_fails_closed_when_legacy_sources_are_not_configured(): void
    {
        config()->set('database.connections.legacy.database', '');
        config()->set('database.connections.legacy_phase1.database', '');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('could not be verified');

        app(CfaPhoneRegistryService::class)->assertAvailableForNewCfa('9876543210');
    }
}
