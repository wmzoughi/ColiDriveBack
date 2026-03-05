<?php
// database/migrations/xxxx_create_account_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('partner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('merchant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('move_id')->nullable()->constrained('account_moves')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('card');
            $table->string('payment_reference')->nullable();
            $table->string('state')->default('draft');
            $table->timestamp('payment_date');
            $table->string('communication')->nullable();
            $table->timestamps();
            
            // Index
            $table->index('partner_id');
            $table->index('merchant_id');
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payments');
    }
};