<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{code: string, name: string, mis_entry_label: string}>
     */
    private array $rows = [
        [
            'code' => 'partners_outreach',
            'name' => 'No of Partners outreach',
            'mis_entry_label' => 'Marketing partner outreach',
        ],
        [
            'code' => 'marketing_partners_onboarded',
            'name' => 'Marketing Partners Onboarded through (LoA/LoI/MoU)',
            'mis_entry_label' => 'Marketing partners onboarded (LoA/LoI/MoU)',
        ],
        [
            'code' => 'product_development_proposal',
            'name' => 'Identification and Submission of Proposal for New Product Development',
            'mis_entry_label' => 'Product development proposals',
        ],
    ];

    public function up(): void
    {
        $maxSort = (int) DB::table('deliverables')->max('sort_order');

        foreach ($this->rows as $row) {
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
        DB::table('deliverables')->whereIn('code', array_column($this->rows, 'code'))->delete();
    }
};
