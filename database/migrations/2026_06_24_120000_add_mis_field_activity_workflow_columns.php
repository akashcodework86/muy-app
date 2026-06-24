<?php

use App\Models\ServiceCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'technical_trainings',
        'potential_lakhpati_technical_trainings',
        'line_department_meetings',
        'community_organization_outreach_visits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'status')) {
                    $table->string('status', 32)->default(ServiceCase::STATUS_PENDING_APPROVAL)->after('submitted_by_name');
                }
                if (! Schema::hasColumn($tableName, 'spoc_user_id')) {
                    $table->unsignedBigInteger('spoc_user_id')->nullable()->after('status');
                }
                if (! Schema::hasColumn($tableName, 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('spoc_user_id');
                }
                if (! Schema::hasColumn($tableName, 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('submitted_at');
                }
                if (! Schema::hasColumn($tableName, 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn($tableName, 'sent_back_note')) {
                    $table->text('sent_back_note')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn($tableName, 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('sent_back_note');
                }
                if (! Schema::hasColumn($tableName, 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
                }
                if (! Schema::hasColumn($tableName, 'rejected_note')) {
                    $table->text('rejected_note')->nullable()->after('rejected_by');
                }

                $statusIndex = substr($tableName, 0, 8).'_status_idx';
                if (! $this->indexExists($tableName, $statusIndex)) {
                    $table->index(['status', 'updated_at'], $statusIndex);
                }
            });

            DB::table($tableName)->whereNull('submitted_at')->update([
                'status' => ServiceCase::STATUS_APPROVED,
                'submitted_at' => DB::raw('COALESCE(submitted_at, created_at)'),
                'approved_at' => DB::raw('COALESCE(approved_at, created_at)'),
                'approved_by' => DB::raw('COALESCE(approved_by, submitted_by_user_id)'),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $statusIndex = substr($tableName, 0, 8).'_status_idx';
                if ($this->indexExists($tableName, $statusIndex)) {
                    $table->dropIndex($statusIndex);
                }

                foreach ([
                    'status',
                    'spoc_user_id',
                    'submitted_at',
                    'approved_at',
                    'approved_by',
                    'sent_back_note',
                    'rejected_at',
                    'rejected_by',
                    'rejected_note',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($row) => ($row->name ?? '') === $index);
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index],
        );

        return (int) ($result[0]->c ?? 0) > 0;
    }
};
