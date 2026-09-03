<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_packages')) {
            return;
        }

        Schema::table('training_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_packages', 'is_draft')) {
                $table->boolean('is_draft')->default(false)->after('selected_incubatees_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_packages')) {
            return;
        }

        Schema::table('training_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('training_packages', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }
};
