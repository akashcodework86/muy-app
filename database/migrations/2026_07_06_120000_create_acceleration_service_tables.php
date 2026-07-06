<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceleration_service_item_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 32);
            $table->string('item_key', 64)->unique();
            $table->string('item_label', 191);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['section', 'is_active']);
        });

        Schema::create('acceleration_service_sessions', function (Blueprint $table): void {
            $table->id();
            $table->date('service_date');
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->unsignedBigInteger('legacy_phase1_application_id');
            $table->string('incubatee_key', 64);
            $table->string('incubatee_source', 16)->default('phase1');
            $table->string('applicant_name', 191);
            $table->string('application_no', 64)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('district_name', 191)->nullable();
            $table->string('onboard_label', 64)->nullable();
            $table->boolean('counts_for_7_2')->default(false);
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('service_date');
            $table->index('incubatee_key');
            $table->index(['fiscal_year_id', 'incubatee_key', 'counts_for_7_2'], 'accel_fy_incubatee_72');
            $table->index('legacy_phase1_application_id');
            $table->index('submitted_by_user_id');
        });

        Schema::create('acceleration_service_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->string('section', 32);
            $table->string('item_key', 64);
            $table->string('item_label', 191);
            $table->text('remarks')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_buyer_seller_meet')->default(false);
            $table->timestamps();

            $table->index('session_id');
            $table->index('item_key');
            $table->index('is_buyer_seller_meet');
        });

        Schema::create('acceleration_service_item_media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name', 191);
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index('item_id');
        });

        (new \Database\Seeders\AccelerationServiceCatalogSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('acceleration_service_item_media');
        Schema::dropIfExists('acceleration_service_items');
        Schema::dropIfExists('acceleration_service_sessions');
        Schema::dropIfExists('acceleration_service_item_catalog');
    }
};
