<?php
// database/migrations/xxxx_create_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            $table->string('packaging')->nullable();
            $table->boolean('is_promotion')->default(false);
            $table->decimal('promotion_price', 10, 2)->nullable();
            $table->timestamp('promotion_start')->nullable();
            $table->timestamp('promotion_end')->nullable();
            $table->integer('popular_rank')->default(0);
            $table->decimal('list_price', 10, 2);
            $table->string('default_code')->nullable()->unique();
            $table->string('type')->default('product');
            $table->string('detailed_type')->default('product');
            $table->foreignId('categ_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->integer('uom_id')->default(1);
            $table->integer('uom_po_id')->default(1);
            $table->decimal('volume', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->string('sale_line_warn')->default('no-message');
            $table->string('purchase_line_warn')->default('no-message');
            $table->boolean('sale_ok')->default(true);
            $table->boolean('purchase_ok')->default(true);
            $table->string('tracking')->default('none');
            $table->string('image_url')->nullable();
            $table->timestamps();
            
            // Index
            $table->index('supplier_id');
            $table->index('categ_id');
            $table->index('is_promotion');
            $table->index('popular_rank');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};