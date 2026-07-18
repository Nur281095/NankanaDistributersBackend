<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_logs', function (Blueprint $table): void {
            $table->index(['changed_by_type', 'changed_by']);
        });
    }

    public function down(): void
    {
        Schema::table('order_status_logs', function (Blueprint $table): void {
            $table->dropIndex(['changed_by_type', 'changed_by']);
        });
    }
};
