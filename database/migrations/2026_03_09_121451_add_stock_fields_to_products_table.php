<?php
// database/migrations/xxxx_add_stock_fields_to_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->after('list_price');
            $table->integer('min_stock_alert')->default(5)->after('stock_quantity');
            $table->integer('max_stock_alert')->default(100)->after('min_stock_alert');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'min_stock_alert', 'max_stock_alert']);
        });
    }
};