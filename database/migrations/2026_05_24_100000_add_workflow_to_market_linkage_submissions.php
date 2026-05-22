<?php

use App\Models\ServiceCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_linkage_submissions', function (Blueprint $table): void {
            $table->string('status', 32)->default(ServiceCase::STATUS_PENDING_APPROVAL)->after('application_no');
            $table->unsignedBigInteger('spoc_user_id')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('spoc_user_id');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->text('sent_back_note')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('sent_back_note');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            $table->text('rejected_note')->nullable()->after('rejected_by');
            $table->timestamp('sla_deadline_at')->nullable()->after('rejected_note');

            $table->index(['district_id', 'status'], 'mls_district_status_idx');
            $table->index(['status', 'updated_at'], 'mls_status_updated_idx');
            $table->index('spoc_user_id', 'mls_spoc_idx');

            $table->foreign('spoc_user_id', 'fk_mls_spoc')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('approved_by', 'fk_mls_approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('rejected_by', 'fk_mls_rejected_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::table('market_linkage_submissions')->update([
            'status' => ServiceCase::STATUS_APPROVED,
            'submitted_at' => DB::raw('created_at'),
            'approved_at' => DB::raw('created_at'),
            'approved_by' => DB::raw('submitted_by_user_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('market_linkage_submissions', function (Blueprint $table): void {
            $table->dropForeign('fk_mls_spoc');
            $table->dropForeign('fk_mls_approved_by');
            $table->dropForeign('fk_mls_rejected_by');
            $table->dropIndex('mls_district_status_idx');
            $table->dropIndex('mls_status_updated_idx');
            $table->dropIndex('mls_spoc_idx');
            $table->dropColumn([
                'status',
                'spoc_user_id',
                'submitted_at',
                'approved_at',
                'approved_by',
                'sent_back_note',
                'rejected_at',
                'rejected_by',
                'rejected_note',
                'sla_deadline_at',
            ]);
        });
    }
};
