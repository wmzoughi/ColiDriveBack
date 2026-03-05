<?php
// database/migrations/xxxx_create_sale_order_lines_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sale_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->nullOnDelete();
            $table->string('name');
            $table->decimal('product_uom_qty', 10, 2)->default(1);
            $table->decimal('price_unit', 10, 2);
            $table->decimal('price_subtotal', 10, 2);
            $table->decimal('price_total', 10, 2);
            $table->decimal('price_tax', 10, 2)->default(0);
            $table->string('packaging')->nullable();
            $table->boolean('promotion_applied')->default(false);
            $table->integer('product_uom')->default(1);
            $table->decimal('discount', 5, 2)->default(0);
            $table->string('state')->default('draft');
            $table->integer('customer_lead')->default(0);
            $table->timestamps();
            
            // Index
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_order_lines');
    }
};