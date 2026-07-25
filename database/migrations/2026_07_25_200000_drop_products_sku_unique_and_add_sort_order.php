<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        $indexes = collect($sm->getIndexes('products'));
        $hasSkuUnique = $indexes->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['sku_code']
        );

        Schema::table('products', function (Blueprint $table) use ($hasSkuUnique): void {
            if ($hasSkuUnique) {
                $table->dropUnique(['sku_code']);
            }

            if (! Schema::hasColumn('products', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_suggested');
                $table->index('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'sort_order')) {
                $table->dropIndex(['sort_order']);
                $table->dropColumn('sort_order');
            }

            $table->unique('sku_code');
        });
    }
};
