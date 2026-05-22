<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_linkage_partner_names', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('normalized_key', 191);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique('normalized_key', 'mlpn_normalized_key_unique');
            $table->index('name', 'mlpn_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_linkage_partner_names');
    }
};
