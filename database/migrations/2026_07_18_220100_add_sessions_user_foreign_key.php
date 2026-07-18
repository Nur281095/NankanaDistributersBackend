<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally no FK on sessions.user_id.
        // The session table is used by both the customer (web) and Filament (admin)
        // guards; DatabaseSessionHandler stores Auth::id() for the active guard,
        // so constraining user_id to users.id breaks admin sessions.
        DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);
    }

    public function down(): void
    {
        //
    }
};
