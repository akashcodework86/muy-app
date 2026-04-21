<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('service_cases', 'delivered_on')) {
                $table->date('delivered_on')->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('service_cases', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('delivered_on');
            }
            if (! Schema::hasColumn('service_cases', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_cases', 'spoc_user_id')) {
                $table->foreignId('spoc_user_id')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_cases', 'approval_snapshot')) {
                $table->json('approval_snapshot')->nullable()->after('spoc_user_id');
            }
            if (! Schema::hasColumn('service_cases', 'sent_back_note')) {
                $table->text('sent_back_note')->nullable()->after('approval_snapshot');
            }
            if (! Schema::hasColumn('service_cases', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('sent_back_note');
            }
            if (! Schema::hasColumn('service_cases', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_cases', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('service_cases', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_cases', 'rejected_note')) {
                $table->text('rejected_note')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('service_cases', 'sla_deadline_at')) {
                $table->timestamp('sla_deadline_at')->nullable()->after('rejected_note');
            }
        });

        // Widen status for new lifecycle values (MySQL). SQLite ignores — text is unbounded.
        if (Schema::hasColumn('service_cases', 'status') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE service_cases MODIFY status VARCHAR(32) NOT NULL');
        }

        // Backfill legacy statuses.
        DB::table('service_cases')->where('status', 'open')->update(['status' => 'draft']);
        DB::table('service_cases')->where('status', 'completed')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('COALESCE(completed_at, updated_at)'),
        ]);
    }

    public function down(): void
    {
        DB::table('service_cases')->where('status', 'draft')->update(['status' => 'open']);
        DB::table('service_cases')->where('status', 'approved')->update([
            'status' => 'completed',
            'completed_at' => DB::raw('COALESCE(approved_at, completed_at, updated_at)'),
            'approved_at' => null,
            'approved_by' => null,
        ]);

        Schema::table('service_cases', function (Blueprint $table) {
            foreach ([
                'sla_deadline_at',
                'rejected_note',
                'rejected_by',
                'rejected_at',
                'approved_by',
                'approved_at',
                'sent_back_note',
                'approval_snapshot',
                'spoc_user_id',
                'submitted_by',
                'submitted_at',
                'delivered_on',
            ] as $col) {
                if (Schema::hasColumn('service_cases', $col)) {
                    if (in_array($col, ['spoc_user_id', 'submitted_by', 'approved_by', 'rejected_by'], true)) {
                        $table->dropForeign([$col]);
                    }
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('service_cases', 'status') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE service_cases MODIFY status VARCHAR(24) NOT NULL');
        }
    }
};
