<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_guest')->default(false);
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();
            $table->string('delivery_address', 500);
            $table->string('city', 100)->nullable();
            $table->string('area', 100)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_charges', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);
            $table->string('payment_method', 20);
            $table->string('payment_status', 20);
            $table->string('order_status', 20);
            $table->string('notes', 500)->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamp('cancellation_deadline')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('guest_customer_id');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('payment_method');
            $table->index('created_at');
            $table->index(['order_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
