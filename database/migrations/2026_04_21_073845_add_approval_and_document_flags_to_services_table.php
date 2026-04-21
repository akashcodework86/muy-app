<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-service toggles used by the maker-checker service flow:
 *  - requires_approval: if true, cases go to pending_approval and route to the
 *    district's SPOC for check; if false, cases auto-approve (legacy behaviour).
 *  - requires_document: if true, staff must upload at least one supporting file
 *    when submitting the service case.
 *  - allowed_document_types: optional JSON list of allowed high-level types
 *    (e.g. ["pdf", "image"]). Null means default (pdf + image).
 *
 * Changing requires_approval does NOT affect in-flight cases; the existing case
 * completes with whatever rule was in force at creation (tracked on the case row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->after('allows_multiple');
            }
            if (! Schema::hasColumn('services', 'requires_document')) {
                $table->boolean('requires_document')->default(false)->after('requires_approval');
            }
            if (! Schema::hasColumn('services', 'allowed_document_types')) {
                $table->json('allowed_document_types')->nullable()->after('requires_document');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'allowed_document_types')) {
                $table->dropColumn('allowed_document_types');
            }
            if (Schema::hasColumn('services', 'requires_document')) {
                $table->dropColumn('requires_document');
            }
            if (Schema::hasColumn('services', 'requires_approval')) {
                $table->dropColumn('requires_approval');
            }
        });
    }
};
