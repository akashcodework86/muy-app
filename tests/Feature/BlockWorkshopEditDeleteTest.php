<?php

namespace Tests\Feature;

use App\Models\BlockWorkshop;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\GramPanchayat;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlockWorkshopEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitter_can_edit_workshop_from_district_view_flow(): void
    {
        if (! Schema::hasTable('block_workshops')) {
            $this->markTestSkipped('block_workshops table not migrated.');
        }

        [$staff, $block, $gp, , $workshop] = $this->createSubmittedWorkshop();

        $this->actingAs($staff)
            ->put(route('staff.workshops.update', $workshop), [
                'visit_date' => '2026-05-25',
                'district_block_id' => $block->id,
                'gram_panchayat_id' => $gp->id,
                'area' => 'Updated village',
                'participants_male_count' => 10,
                'participants_female_count' => 8,
                'remark' => 'Corrected counts',
            ])
            ->assertRedirect(route('staff.workshops.view'));

        $workshop->refresh();
        $this->assertSame(10, $workshop->participants_male_count);
        $this->assertSame(8, $workshop->participants_female_count);
        $this->assertSame(18, $workshop->participants_total);
        $this->assertSame('Updated village', $workshop->area);
    }

    public function test_submitter_can_delete_own_workshop_from_view(): void
    {
        if (! Schema::hasTable('block_workshops')) {
            $this->markTestSkipped('block_workshops table not migrated.');
        }

        Storage::fake();

        [$staff, , , , $workshop] = $this->createSubmittedWorkshop();

        $this->actingAs($staff)
            ->delete(route('staff.workshops.destroy', $workshop), ['from_view' => 1])
            ->assertRedirect(route('staff.workshops.view'));

        $this->assertDatabaseMissing('block_workshops', ['id' => $workshop->id]);
    }

    public function test_district_view_shows_edit_delete_only_for_submitter(): void
    {
        if (! Schema::hasTable('block_workshops')) {
            $this->markTestSkipped('block_workshops table not migrated.');
        }

        [$staff, , , , $workshop] = $this->createSubmittedWorkshop();

        $other = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $staff->district_id,
            'designation_id' => $staff->designation_id,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.workshops.view'))
            ->assertOk()
            ->assertSee('Edit')
            ->assertSee('Delete');

        $this->actingAs($other)
            ->get(route('staff.workshops.view'))
            ->assertOk()
            ->assertSee('View')
            ->assertDontSee('>Edit<', false)
            ->assertDontSee('Delete this workshop submission?');

        $this->actingAs($other)
            ->put(route('staff.workshops.update', $workshop), [
                'visit_date' => '2026-05-25',
                'district_block_id' => $workshop->district_block_id,
                'gram_panchayat_id' => $workshop->gram_panchayat_id,
                'area' => 'Hack',
                'participants_male_count' => 1,
                'participants_female_count' => 0,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: DistrictBlock, 2: GramPanchayat, 3: District, 4: BlockWorkshop}
     */
    private function createSubmittedWorkshop(): array
    {
        $district = District::query()->create([
            'hub_id' => Hub::query()->firstOrCreate(['slug' => 'bw-edit-hub'], ['name' => 'Hub', 'sort_order' => 1])->id,
            'slug' => 'bageshwar-bw',
            'name' => 'Bageshwar',
            'sort_order' => 1,
        ]);
        $designation = Designation::query()->create(['name' => 'Field Coordinator', 'sort_order' => 1]);
        $staff = User::factory()->create([
            'role' => 'district_staff',
            'district_id' => $district->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'name' => 'Atul Mohan',
        ]);
        $block = DistrictBlock::query()->create([
            'district_id' => $district->id,
            'name' => 'Garur',
            'sort_order' => 0,
        ]);
        $gp = GramPanchayat::query()->create([
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'name' => 'Demo GP',
        ]);
        $workshop = BlockWorkshop::query()->create([
            'field_coordinator_user_id' => $staff->id,
            'field_coordinator_name' => $staff->name,
            'visit_date' => '2026-05-25',
            'entry_date' => '2026-05-25',
            'district_id' => $district->id,
            'district_block_id' => $block->id,
            'gram_panchayat_id' => $gp->id,
            'block' => $block->name,
            'area' => 'Demo Area',
            'status' => BlockWorkshop::STATUS_SUBMITTED,
            'participants_male_count' => 9,
            'participants_female_count' => 9,
            'participants_total' => 18,
            'participants_json' => [],
        ]);

        return [$staff, $block, $gp, $district, $workshop];
    }
}
