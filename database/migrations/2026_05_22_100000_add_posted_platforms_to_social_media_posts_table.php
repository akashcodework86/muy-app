<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->json('posted_platforms')->nullable()->after('post_url');
        });
    }

    public function down(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->dropColumn('posted_platforms');
        });
    }
};
