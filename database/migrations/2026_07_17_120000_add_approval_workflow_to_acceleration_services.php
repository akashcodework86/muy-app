<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acceleration_service_sessions') && ! Schema::hasColumn('acceleration_service_sessions', 'status')) {
            Schema::table('acceleration_service_sessions', function (Blueprint $table): void {
                $table->string('status', 32)->default('pending_review')->index();

                $table->unsignedBigInteger('first_approved_by_user_id')->nullable();
                $table->string('first_approved_by_name')->nullable();
                $table->timestamp('first_approved_at')->nullable();

                $table->unsignedBigInteger('final_approved_by_user_id')->nullable();
                $table->string('final_approved_by_name')->nullable();
                $table->timestamp('final_approved_at')->nullable();

                $table->unsignedBigInteger('sent_back_by_user_id')->nullable();
                $table->string('sent_back_by_name')->nullable();
                $table->timestamp('sent_back_at')->nullable();
                $table->text('sent_back_remarks')->nullable();
            });

            // Entries created before the maker-checker workflow stay counted.
            DB::table('acceleration_service_sessions')->update(['status' => 'approved']);
            if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                DB::table('acceleration_service_sessions')->where('is_draft', true)->update(['status' => 'draft']);
            }
        }

        if (! Schema::hasTable('acceleration_service_session_events')) {
            Schema::create('acceleration_service_session_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('session_id')
                    ->constrained('acceleration_service_sessions')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_name')->default('');
                $table->string('actor_role', 32)->default('');
                $table->string('action', 48)->index();
                $table->text('remarks')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acceleration_service_session_events');

        if (Schema::hasTable('acceleration_service_sessions') && Schema::hasColumn('acceleration_service_sessions', 'status')) {
            Schema::table('acceleration_service_sessions', function (Blueprint $table): void {
                $table->dropColumn([
                    'status',
                    'first_approved_by_user_id',
                    'first_approved_by_name',
                    'first_approved_at',
                    'final_approved_by_user_id',
                    'final_approved_by_name',
                    'final_approved_at',
                    'sent_back_by_user_id',
                    'sent_back_by_name',
                    'sent_back_at',
                    'sent_back_remarks',
                ]);
            });
        }
    }
};
