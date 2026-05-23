<?php

namespace Tests\Unit;

use App\Services\BlockWorkshopParticipantRowsService;
use PHPUnit\Framework\TestCase;

class BlockWorkshopParticipantRowsServiceTest extends TestCase
{
    public function test_sync_row_count_assigns_default_gender_slots(): void
    {
        $service = new BlockWorkshopParticipantRowsService;

        $rows = $service->syncRowCount(
            null,
            2,
            1,
            'Almora',
            'Ranikhet',
            10,
            'Default GP',
        );

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0]['sr']);
        $this->assertSame('M', $rows[0]['gender']);
        $this->assertSame('M', $rows[1]['gender']);
        $this->assertSame('F', $rows[2]['gender']);
        $this->assertSame('Almora', $rows[0]['district_name']);
        $this->assertSame('Ranikhet', $rows[0]['block_name']);
        $this->assertSame(10, $rows[0]['gram_panchayat_id']);
    }

    public function test_participant_counts_by_gram_panchayat_groups_rows(): void
    {
        $report = new \App\Models\FieldCoordinatorAttendanceReport([
            'participants_json' => [
                ['gram_panchayat_name' => 'GP Alpha'],
                ['gram_panchayat_name' => 'GP Alpha'],
                ['gram_panchayat_name' => 'GP Beta'],
                ['gram_panchayat_name' => ''],
            ],
            'participants_total' => 4,
        ]);

        $counts = $report->participantCountsByGramPanchayat();

        $this->assertCount(3, $counts);
        $alpha = collect($counts)->firstWhere('name', 'GP Alpha');
        $this->assertNotNull($alpha);
        $this->assertSame(2, $alpha['count']);
    }

    public function test_sanitize_strips_invalid_gender(): void
    {
        $service = new BlockWorkshopParticipantRowsService;

        $rows = $service->sanitizeIncoming([
            ['name' => 'A', 'gender' => 'X', 'mobile' => '98-7654-3210'],
        ], 1);

        $this->assertSame('', $rows[0]['gender']);
        $this->assertSame('9876543210', $rows[0]['mobile']);
    }
}
