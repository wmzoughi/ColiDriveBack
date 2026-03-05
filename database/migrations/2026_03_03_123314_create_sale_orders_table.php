<?php
// database/migrations/xxxx_create_sale_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('partner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            $table->string('delivery_status')->default('pending');
            $table->string('state')->default('draft');
            $table->decimal('amount_total', 10, 2)->default(0);
            $table->decimal('amount_tax', 10, 2)->default(0);
            $table->decimal('amount_untaxed', 10, 2)->default(0);
            $table->timestamp('date_order')->nullable();
            $table->date('validity_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('company_id')->nullable();
            $table->string('invoice_status')->default('no');
            $table->text('note')->nullable();
            $table->string('client_order_ref')->nullable();
            $table->string('origin')->nullable();
            $table->timestamps();
            
            // Index
            $table->index('partner_id');
            $table->index('supplier_id');
            $table->index('delivery_status');
            $table->index('state');
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};