<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_package_month_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('district_id');
            $table->unsignedSmallInteger('calendar_year');
            $table->unsignedTinyInteger('calendar_month');
            $table->unsignedInteger('sort_order');
            $table->string('session_name', 191);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['district_id', 'calendar_year', 'calendar_month', 'sort_order'],
                'tp_month_session_district_period_order_unique'
            );
            $table->index(['calendar_year', 'calendar_month'], 'tp_month_session_period_idx');

            $table->foreign('district_id', 'fk_tp_month_session_district')
                ->references('id')
                ->on('districts')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'fk_tp_month_session_created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by_user_id', 'fk_tp_month_session_updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('training_packages', function (Blueprint $table): void {
            $table->unsignedBigInteger('month_session_id')->nullable()->after('district_name');

            $table->unique('month_session_id', 'tp_month_session_id_unique');
            $table->foreign('month_session_id', 'fk_training_package_month_session')
                ->references('id')
                ->on('training_package_month_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_packages', function (Blueprint $table): void {
            $table->dropForeign('fk_training_package_month_session');
            $table->dropUnique('tp_month_session_id_unique');
            $table->dropColumn('month_session_id');
        });

        Schema::dropIfExists('training_package_month_sessions');
    }
};
