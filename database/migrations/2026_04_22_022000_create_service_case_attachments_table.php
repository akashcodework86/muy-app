<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_case_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_case_id')->constrained('service_cases')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('service_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_case_attachments');
    }
};
