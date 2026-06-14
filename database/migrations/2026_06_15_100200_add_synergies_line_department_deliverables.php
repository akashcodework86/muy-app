<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'code' => 'stakeholder_consultation_workshop',
                'name' => 'Stakeholder Consultation Workshop',
                'mis_entry_label' => 'Stakeholder consultation workshops',
            ],
            [
                'code' => 'line_department_meeting',
                'name' => 'Meeting of staff with Line Department at Spoke/Hub/State Level',
                'mis_entry_label' => 'Line department meetings',
            ],
        ];

        $maxSort = (int) DB::table('deliverables')->max('sort_order');

        foreach ($rows as $row) {
            if (DB::table('deliverables')->where('code', $row['code'])->exists()) {
                continue;
            }

            $maxSort++;
            DB::table('deliverables')->insert([
                'sort_order' => $maxSort,
                'code' => $row['code'],
                'name' => $row['name'],
                'mis_entry_label' => $row['mis_entry_label'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('deliverables')->whereIn('code', [
            'stakeholder_consultation_workshop',
            'line_department_meeting',
        ])->delete();
    }
};
