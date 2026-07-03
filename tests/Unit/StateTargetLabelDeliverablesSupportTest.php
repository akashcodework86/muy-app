<?php

namespace Tests\Unit;

use App\Support\StateTargetLabelDeliverablesSupport;
use Tests\TestCase;

class StateTargetLabelDeliverablesSupportTest extends TestCase
{
    public function test_identifies_line_department_meeting_row(): void
    {
        $this->assertTrue(StateTargetLabelDeliverablesSupport::isStateTargetLabelRow('12.2'));
        $this->assertTrue(StateTargetLabelDeliverablesSupport::isStateTargetLabelRow('', [
            'type' => 'line_department_meeting_sessions',
            'deliverable_code' => 'line_department_meeting',
        ]));
        $this->assertFalse(StateTargetLabelDeliverablesSupport::isStateTargetLabelRow('12.1'));
    }
}
