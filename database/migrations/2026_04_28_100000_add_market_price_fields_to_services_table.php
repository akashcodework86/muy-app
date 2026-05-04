<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('services', 'estimated_market_price_avg')
            && Schema::hasColumn('services', 'estimated_market_price_min')
            && Schema::hasColumn('services', 'estimated_market_price_max')
            && Schema::hasColumn('services', 'market_price_basis_note')
        ) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'estimated_market_price_avg')) {
                $table->decimal('estimated_market_price_avg', 12, 2)->nullable()->after('name');
            }
            if (! Schema::hasColumn('services', 'estimated_market_price_min')) {
                $table->decimal('estimated_market_price_min', 12, 2)->nullable()->after('estimated_market_price_avg');
            }
            if (! Schema::hasColumn('services', 'estimated_market_price_max')) {
                $table->decimal('estimated_market_price_max', 12, 2)->nullable()->after('estimated_market_price_min');
            }
            if (! Schema::hasColumn('services', 'market_price_basis_note')) {
                $table->text('market_price_basis_note')->nullable()->after('estimated_market_price_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $cols = [];
            foreach (['estimated_market_price_avg', 'estimated_market_price_min', 'estimated_market_price_max', 'market_price_basis_note'] as $col) {
                if (Schema::hasColumn('services', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
