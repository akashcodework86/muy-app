<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_organization_outreach_visits', function (Blueprint $table): void {
            $table->string('organization_type_other', 191)->nullable()->after('organization_type');
            $table->json('documents_json')->nullable()->after('remarks');
            $table->json('photos_json')->nullable()->after('documents_json');
        });
    }

    public function down(): void
    {
        Schema::table('community_organization_outreach_visits', function (Blueprint $table): void {
            $table->dropColumn(['organization_type_other', 'documents_json', 'photos_json']);
        });
    }
};
