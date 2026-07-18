<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->cascadeOnDelete();
        });
    }
};
