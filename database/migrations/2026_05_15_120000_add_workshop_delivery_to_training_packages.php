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
            if (! Schema::hasColumn('training_packages', 'workshop_delivery')) {
                $table->string('workshop_delivery', 16)
                    ->default('physical')
                    ->after('training_batch_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_packages')) {
            return;
        }

        Schema::table('training_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('training_packages', 'workshop_delivery')) {
                $table->dropColumn('workshop_delivery');
            }
        });
    }
};
