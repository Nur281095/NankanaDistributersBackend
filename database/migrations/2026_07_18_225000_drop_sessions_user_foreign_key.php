<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Database sessions are shared by the customer (web) and admin guards.
        // Only drop the FK when it actually exists (fresh installs may never create it).
        if (! $this->foreignKeyExists('sessions', 'sessions_user_id_foreign')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('sessions', 'sessions_user_id_foreign')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite has no information_schema.table_constraints like MySQL.
            $foreignKeys = DB::select("pragma foreign_key_list({$table})");

            foreach ($foreignKeys as $foreignKey) {
                if (($foreignKey->table ?? null) === 'users' && ($foreignKey->from ?? null) === 'user_id') {
                    return true;
                }
            }

            return false;
        }

        $result = DB::selectOne(
            'select constraint_name
             from information_schema.table_constraints
             where table_schema = database()
               and table_name = ?
               and constraint_type = \'FOREIGN KEY\'
               and constraint_name = ?
             limit 1',
            [$table, $constraint],
        );

        return $result !== null;
    }
};
