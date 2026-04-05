<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defaults = [
            ['State Admin', 1],
            ['MUY Spoke', 2],
            ['CDO', 3],
            ['District Staff', 4],
            ['Hub Coordinator', 5],
            ['Block / Field Staff', 6],
        ];

        foreach ($defaults as [$name, $order]) {
            if (! DB::table('designations')->where('name', $name)->exists()) {
                DB::table('designations')->insert([
                    'name' => $name,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('designation_id')->nullable()->after('role')->constrained('designations')->nullOnDelete();
        });

        if (Schema::hasColumn('users', 'designation')) {
            /** @var array<string, int> $nameToId */
            $nameToId = DB::table('designations')->pluck('id', 'name')->all();

            foreach (DB::table('users')->whereNotNull('designation')->cursor() as $row) {
                $name = trim((string) $row->designation);
                if ($name === '') {
                    continue;
                }
                if (! isset($nameToId[$name])) {
                    $id = DB::table('designations')->insertGetId([
                        'name' => $name,
                        'sort_order' => 99,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $nameToId[$name] = $id;
                }
                DB::table('users')->where('id', $row->id)->update([
                    'designation_id' => $nameToId[$name],
                ]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('designation');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('designation', 120)->nullable()->after('role');
        });

        foreach (DB::table('users')->whereNotNull('designation_id')->cursor() as $row) {
            $name = DB::table('designations')->where('id', $row->designation_id)->value('name');
            DB::table('users')->where('id', $row->id)->update(['designation' => $name]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
        });
    }
};
