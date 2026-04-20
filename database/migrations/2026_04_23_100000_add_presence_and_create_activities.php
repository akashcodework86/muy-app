<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->index();
            }
        });

        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 64)->index();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->string('actor_role', 32)->nullable()->index();
                $table->string('actor_name')->nullable();
                $table->string('subject_type', 120)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedBigInteger('district_id')->nullable()->index();
                $table->unsignedBigInteger('hub_id')->nullable()->index();
                $table->string('title', 240);
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['subject_type', 'subject_id'], 'activities_subject_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }
        });
    }
};
