<?php
// database/migrations/xxxx_create_product_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('complete_name')->nullable();
            $table->integer('popular_rank')->default(0);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            // Index
            $table->index('parent_id');
            $table->index('popular_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};