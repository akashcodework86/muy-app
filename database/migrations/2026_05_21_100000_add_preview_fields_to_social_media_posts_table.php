<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->string('platform', 64)->nullable()->after('post_url');
            $table->string('thumbnail_url', 2048)->nullable()->after('platform');
            $table->string('preview_title', 500)->nullable()->after('thumbnail_url');
        });
    }

    public function down(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->dropColumn(['platform', 'thumbnail_url', 'preview_title']);
        });
    }
};
