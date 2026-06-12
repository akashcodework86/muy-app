<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_cases')) {
            return;
        }

        Schema::table('service_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_cases', 'through_reap')) {
                $table->boolean('through_reap')->default(false)->after('payload');
            }
        });

        if (! Schema::hasColumn('service_cases', 'through_reap')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE service_cases
                SET through_reap = 1
                WHERE LOWER(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.\"through_reap\"')) AS CHAR), '')) IN ('1', 'true', 'yes', 'on')
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_cases') || ! Schema::hasColumn('service_cases', 'through_reap')) {
            return;
        }

        Schema::table('service_cases', function (Blueprint $table): void {
            $table->dropColumn('through_reap');
        });
    }
};
