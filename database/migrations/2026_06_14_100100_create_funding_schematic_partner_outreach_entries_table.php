<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_schematic_partner_outreach_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->date('outreach_date');
            $table->string('outreach_mode', 32);
            $table->string('partner_name', 255);
            $table->string('partner_type', 64);
            $table->string('partner_type_other', 191)->nullable();
            $table->string('contact_name', 191)->nullable();
            $table->string('designation', 191)->nullable();
            $table->string('poc_phone', 15)->nullable();
            $table->string('partner_link', 2048)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_name', 191);
            $table->timestamps();

            $table->index('batch_id', 'fspoe_batch_idx');
            $table->index('outreach_date', 'fspoe_outreach_date_idx');
            $table->index('submitted_by_user_id', 'fspoe_submitter_idx');
            $table->index('partner_name', 'fspoe_partner_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_schematic_partner_outreach_entries');
    }
};
