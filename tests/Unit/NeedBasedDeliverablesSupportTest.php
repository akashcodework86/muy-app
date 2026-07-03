<?php

namespace Tests\Unit;

use App\Support\NeedBasedDeliverablesSupport;
use Tests\TestCase;

class NeedBasedDeliverablesSupportTest extends TestCase
{
    public function test_identifies_need_based_rows_by_serial(): void
    {
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('3.3.1'));
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('4.2.7'));
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('5.2'));
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('9.2'));
        $this->assertFalse(NeedBasedDeliverablesSupport::isNeedBasedRow('3.3'));
        $this->assertFalse(NeedBasedDeliverablesSupport::isNeedBasedRow('4.2.3'));
    }

    public function test_identifies_need_based_rows_by_deliverable_code(): void
    {
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('', [
            'type' => 'deliverable',
            'code' => 'advance_licensing_support',
        ]));
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('', [
            'type' => 'technical_training_potential_lakhpati_sessions',
            'deliverable_code' => 'technical_training_potential_lakhpati',
        ]));
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('', [
            'type' => 'deliverable',
            'code' => 'mentorship_online_portal',
        ]));
        $this->assertFalse(NeedBasedDeliverablesSupport::isNeedBasedRow('', [
            'type' => 'deliverable',
            'code' => 'gst',
        ]));
    }

    public function test_identifies_need_based_rows_by_service_code(): void
    {
        $this->assertTrue(NeedBasedDeliverablesSupport::isNeedBasedRow('', [
            'type' => 'service',
            'code' => 'other_support_services_labelling_packaging_logo_designing_etc',
        ]));
    }
}
