<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column was already added via direct SQL; this migration is a no-op
        // if the column exists, so we guard before altering.
        if (! Schema::hasColumn('cfa_submissions', 'source')) {
            Schema::table('cfa_submissions', function (Blueprint $table) {
                $table->string('source', 30)
                    ->default('referral')
                    ->after('referral_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cfa_submissions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
