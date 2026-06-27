<?php

namespace Tests\Unit;

use App\Support\BstTrainingDeliverablesSupport;
use Tests\TestCase;

class BstTrainingDeliverablesSupportTest extends TestCase
{
    public function test_module_aware_participation_counts_once_per_session_for_multi_module_and_skips_repeat_modules(): void
    {
        $packages = collect([
            (object) [
                'package_id' => 1,
                'selected_incubatee_ids' => [101, 102],
                'selected_incubatees_snapshot' => [
                    ['incubatee_id' => 101, 'name' => 'A'],
                    ['incubatee_id' => 102, 'name' => 'B'],
                ],
                'training_packages' => ['t1'],
                'training_package' => null,
                'event_date' => '2026-05-10',
                'training_batch_name' => 'Batch 1',
                'district_name' => 'Test District',
                'hub_name' => 'Test Hub',
                'session_name' => '',
            ],
            (object) [
                'package_id' => 2,
                'selected_incubatee_ids' => [101, 103],
                'selected_incubatees_snapshot' => [
                    ['incubatee_id' => 101, 'name' => 'A'],
                    ['incubatee_id' => 103, 'name' => 'C'],
                ],
                'training_packages' => ['t2', 't3'],
                'training_package' => null,
                'event_date' => '2026-06-12',
                'training_batch_name' => 'Batch 2',
                'district_name' => 'Test District',
                'hub_name' => 'Test Hub',
                'session_name' => '',
            ],
            (object) [
                'package_id' => 3,
                'selected_incubatee_ids' => [101, 102],
                'selected_incubatees_snapshot' => [
                    ['incubatee_id' => 101, 'name' => 'A'],
                    ['incubatee_id' => 102, 'name' => 'B'],
                ],
                'training_packages' => ['t1'],
                'training_package' => null,
                'event_date' => '2026-06-20',
                'training_batch_name' => 'Batch 3',
                'district_name' => 'Test District',
                'hub_name' => 'Test Hub',
                'session_name' => '',
            ],
        ]);

        $breakdown = BstTrainingDeliverablesSupport::moduleAwareParticipationBreakdownFromPackages($packages);

        $this->assertSame(4, $breakdown['total']);
        $this->assertCount(4, $breakdown['records']);
        $this->assertSame(4, collect($breakdown['by_district'])->sum());
    }
}
