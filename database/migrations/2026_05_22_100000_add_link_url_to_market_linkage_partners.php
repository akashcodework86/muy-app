<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_linkage_partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('market_linkage_partners', 'link_url')) {
                $table->string('link_url', 2048)->nullable()->after('linkage_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_linkage_partners', function (Blueprint $table): void {
            if (Schema::hasColumn('market_linkage_partners', 'link_url')) {
                $table->dropColumn('link_url');
            }
        });
    }
};
