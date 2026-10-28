<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_seller_meet_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('meet_date');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->string('title', 255);
            $table->string('venue', 255);
            $table->unsignedInteger('total_participants');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('meet_date', 'bsme_meet_date_idx');
            $table->index('district_id', 'bsme_district_idx');
            $table->index('fiscal_year_id', 'bsme_fy_idx');
            $table->index('submitted_by_user_id', 'bsme_submitter_idx');
            $table->index(['district_id', 'meet_date'], 'bsme_district_date_idx');

            $table->foreign('district_id', 'fk_bsme_district')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();
            $table->foreign('fiscal_year_id', 'fk_bsme_fy')
                ->references('id')
                ->on('fiscal_years')
                ->nullOnDelete();
            $table->foreign('submitted_by_user_id', 'fk_bsme_submitter')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('buyer_seller_meet_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_seller_meet_entry_id');
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('buyer_seller_meet_entry_id', 'bsmp_entry_idx');

            $table->foreign('buyer_seller_meet_entry_id', 'fk_bsmp_entry')
                ->references('id')
                ->on('buyer_seller_meet_entries')
                ->cascadeOnDelete();
        });

        Schema::create('buyer_seller_meet_attendance_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_seller_meet_entry_id');
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('buyer_seller_meet_entry_id', 'bsmaf_entry_idx');

            $table->foreign('buyer_seller_meet_entry_id', 'fk_bsmaf_entry')
                ->references('id')
                ->on('buyer_seller_meet_entries')
                ->cascadeOnDelete();
        });

        Schema::create('buyer_seller_meet_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('buyer_seller_meet_entry_id');
            $table->string('participant_kind', 16);
            $table->string('role', 16);
            $table->string('incubatee_key', 64)->nullable();
            $table->unsignedBigInteger('cfa_submission_id')->nullable();
            $table->unsignedBigInteger('legacy_application_id')->nullable();
            $table->unsignedBigInteger('legacy_phase1_application_id')->nullable();
            $table->string('participant_name', 255);
            $table->string('application_no', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('buyer_seller_meet_entry_id', 'bsmpart_entry_idx');
            $table->index('role', 'bsmpart_role_idx');

            $table->foreign('buyer_seller_meet_entry_id', 'fk_bsmpart_entry')
                ->references('id')
                ->on('buyer_seller_meet_entries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_seller_meet_participants');
        Schema::dropIfExists('buyer_seller_meet_attendance_files');
        Schema::dropIfExists('buyer_seller_meet_photos');
        Schema::dropIfExists('buyer_seller_meet_entries');
    }
};
