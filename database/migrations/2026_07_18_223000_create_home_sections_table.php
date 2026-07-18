<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('title')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('product_source', 40)->nullable();
            $table->unsignedSmallInteger('product_limit')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index('type');
        });

        Schema::create('home_sliders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->unique()->constrained('home_sections')->cascadeOnDelete();
            $table->boolean('autoplay')->default(true);
            $table->unsignedSmallInteger('interval_ms')->default(4000);
            $table->timestamps();
        });

        Schema::create('home_slider_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_slider_id')->constrained('home_sliders')->cascadeOnDelete();
            $table->string('image', 500);
            $table->string('title')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('link_type', 20)->default('none');
            $table->string('link_value', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['home_slider_id', 'sort_order']);
        });

        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->unique()->constrained('home_sections')->cascadeOnDelete();
            $table->string('image', 500);
            $table->string('title')->nullable();
            $table->string('link_type', 20)->default('none');
            $table->string('link_value', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('home_section_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['home_section_id', 'product_id']);
            $table->index(['home_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_products');
        Schema::dropIfExists('home_banners');
        Schema::dropIfExists('home_slider_slides');
        Schema::dropIfExists('home_sliders');
        Schema::dropIfExists('home_sections');
    }
};
