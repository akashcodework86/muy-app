<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_study_entry_attachments')) {
            return;
        }

        Schema::create('case_study_entry_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('case_study_entry_id');
            $table->string('attachment_type', 32);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('case_study_entry_id', 'csea_entry_idx');

            $table->foreign('case_study_entry_id', 'fk_csea_entry')
                ->references('id')
                ->on('case_study_entries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_study_entry_attachments');
    }
};
