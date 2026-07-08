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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('product_uid')->index();
            $table->string('product_name')->nullable();

            $table->string('category_uid')->nullable()->index();
            $table->string('category_name')->nullable();

            $table->string('seller_uid')->nullable()->index();
            $table->string('seller_name')->nullable();

            $table->string('warehouse_uid')->nullable();
            $table->string('warehouse_name')->nullable();

            $table->string('color_family')->nullable();
            $table->string('size')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('mrp_price', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
