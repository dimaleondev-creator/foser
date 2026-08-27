<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('university_users')
            ->join('users', 'users.id', '=', 'university_users.user_id')
            ->where('users.account_type', 'universite')
            ->whereNull('users.university_id')
            ->select('users.id', 'university_users.university_id')
            ->orderBy('users.id')
            ->get()
            ->each(fn (object $assignment): int => DB::table('users')->where('id', $assignment->id)->update(['university_id' => $assignment->university_id]));
    }

    public function down(): void
    {
        // Associations are intentionally retained in users and university_users.
    }
};