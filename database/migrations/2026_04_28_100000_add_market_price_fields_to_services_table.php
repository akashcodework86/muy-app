<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('estimated_market_price_avg', 12, 2)->nullable()->after('name');
            $table->decimal('estimated_market_price_min', 12, 2)->nullable()->after('estimated_market_price_avg');
            $table->decimal('estimated_market_price_max', 12, 2)->nullable()->after('estimated_market_price_min');
            $table->text('market_price_basis_note')->nullable()->after('estimated_market_price_max');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_market_price_avg',
                'estimated_market_price_min',
                'estimated_market_price_max',
                'market_price_basis_note',
            ]);
        });
    }
};
