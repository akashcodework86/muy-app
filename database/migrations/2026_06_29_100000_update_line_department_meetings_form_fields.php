<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_department_meetings', function (Blueprint $table): void {
            $table->text('agenda_remark_outcome')->nullable()->after('meeting_purpose_other');
            $table->text('muy_staff_present')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('line_department_meetings', function (Blueprint $table): void {
            $table->dropColumn('agenda_remark_outcome');
            $table->text('muy_staff_present')->nullable(false)->change();
        });
    }
};
