<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Designation;
use App\Models\District;
use App\Models\DistrictDeliverableTarget;
use App\Models\FiscalYear;
use App\Models\Hub;
use App\Models\StaffMonthlyTarget;
use App\Models\StateDeliverableTarget;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoTargetSeeder extends Seeder
{
    public function run(): void
    {
        $fy = FiscalYear::query()->where('is_active', true)->orderByDesc('starts_on')->first()
            ?? FiscalYear::query()->orderByDesc('starts_on')->first();
        if (! $fy) {
            return;
        }

        $cfa = Deliverable::query()->where('code', 'cfa')->first();
        $almora = District::query()->where('slug', 'almora')->first();
        $kumaon = Hub::query()->where('slug', 'kumaon')->first();

        if (! $cfa || ! $almora || ! $kumaon) {
            return;
        }

        $stateAdminDes = Designation::query()->where('name', 'State Admin')->value('id');
        $muySpokeId = Designation::query()->where('name', 'MUY Spoke')->value('id');

        User::query()->updateOrCreate(
            ['email' => 'stateadmin@local.test'],
            [
                'name' => 'State Admin',
                'password' => 'password',
                'role' => 'state_admin',
                'designation_id' => $stateAdminDes,
                'hub_id' => null,
                'district_id' => null,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'hubadmin@local.test'],
            [
                'name' => 'Hub Admin (Kumaon)',
                'password' => 'password',
                'role' => 'hub_admin',
                'designation_id' => $stateAdminDes,
                'hub_id' => $kumaon->id,
                'district_id' => null,
            ]
        );

        $staff = User::query()->firstOrNew(['email' => 'staff.almora@local.test']);
        $staff->fill([
            'name' => 'Staff Almora',
            'password' => 'password',
            'role' => 'district_staff',
            'designation_id' => $muySpokeId,
            'hub_id' => $kumaon->id,
            'district_id' => $almora->id,
        ]);
        if (empty($staff->referral_token)) {
            $staff->referral_token = Str::lower(Str::random(40));
        }
        $staff->save();

        // One district with CFA target 1000; state total 1000 (other districts have no row → sum = 1000).
        StateDeliverableTarget::query()->updateOrCreate(
            ['fiscal_year_id' => $fy->id, 'deliverable_id' => $cfa->id],
            ['target_total' => 1_000]
        );

        DistrictDeliverableTarget::query()->updateOrCreate(
            [
                'fiscal_year_id' => $fy->id,
                'district_id' => $almora->id,
                'deliverable_id' => $cfa->id,
            ],
            ['target_total' => 1_000]
        );

        $base = intdiv(1_000, 12);
        $remainder = 1_000 - ($base * 12);
        foreach (range(1, 12) as $month) {
            $count = $base + ($month <= $remainder ? 1 : 0);
            StaffMonthlyTarget::query()->updateOrCreate(
                [
                    'fiscal_year_id' => $fy->id,
                    'user_id' => $staff->id,
                    'deliverable_id' => $cfa->id,
                    'month_number' => $month,
                ],
                ['target_count' => $count]
            );
        }

        $this->command->info('Demo targets: stateadmin@local.test / hubadmin@local.test / staff.almora@local.test (password: password)');
    }
}
