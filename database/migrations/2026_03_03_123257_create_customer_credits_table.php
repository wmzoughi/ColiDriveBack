<?php
// database/migrations/xxxx_create_customer_credits_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            $table->decimal('credit_limit', 10, 2)->default(1000);
            $table->timestamps();
            
            // Index
            $table->index('partner_id');
            $table->index('supplier_id');
            $table->unique(['partner_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};