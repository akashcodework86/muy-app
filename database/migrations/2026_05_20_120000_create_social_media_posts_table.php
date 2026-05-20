<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->date('posted_on');
            $table->string('post_url', 2048);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['submitted_by_user_id', 'posted_on'], 'smp_submitted_by_posted_idx');
            $table->index('posted_on', 'smp_posted_on_idx');

            $table->foreign('submitted_by_user_id', 'fk_social_media_post_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
