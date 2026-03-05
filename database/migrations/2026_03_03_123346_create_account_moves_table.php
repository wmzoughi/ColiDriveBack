<?php
// database/migrations/xxxx_create_account_moves_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_moves', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('partner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('sale_orders')->nullOnDelete();
            $table->string('state')->default('draft');
            $table->string('payment_state')->default('unpaid');
            $table->decimal('amount_total', 10, 2);
            $table->decimal('amount_total_signed', 10, 2);
            $table->decimal('amount_residual', 10, 2);
            $table->decimal('amount_residual_signed', 10, 2);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->timestamps();
            
            // Index
            $table->index('partner_id');
            $table->index('state');
            $table->index('payment_state');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_moves');
    }
};