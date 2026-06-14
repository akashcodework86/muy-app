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
            'code' => 'muy_newsletter',
            'name' => 'MUY Newsletter',
            'mis_entry_label' => 'MUY Newsletter',
        ],
        [
            'code' => 'media_campaigns',
            'name' => 'Newspaper Ads and Radio promotion campaigns',
            'mis_entry_label' => 'Newspaper / Radio campaigns',
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
