<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cfa_submission_id')
                ->nullable()
                ->after('legacy_user_id')
                ->constrained('cfa_submissions')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('cfa_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cfa_submission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cfa_submission_id');
        });
    }
};
