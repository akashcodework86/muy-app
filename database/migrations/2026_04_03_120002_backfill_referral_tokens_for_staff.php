<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('users')
            ->where('role', 'district_staff')
            ->whereNull('referral_token')
            ->pluck('id');

        foreach ($ids as $id) {
            do {
                $token = Str::lower(Str::random(40));
            } while (DB::table('users')->where('referral_token', $token)->exists());

            DB::table('users')->where('id', $id)->update(['referral_token' => $token]);
        }
    }

    public function down(): void
    {
        //
    }
};
