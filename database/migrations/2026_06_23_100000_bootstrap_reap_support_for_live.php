<?php

use App\Support\ConvergenceReapSupport;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ConvergenceReapSupport::bootstrapReapSupportServices();
        ConvergenceReapSupport::backfillThroughReapFlags();
    }

    public function down(): void
    {
        // Data bootstrap only — no rollback.
    }
};
