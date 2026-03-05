<?php
// database/migrations/xxxx_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('user_type')->default('commercant'); // commercant/fournisseur
            $table->string('siret', 14)->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->decimal('credit_balance', 10, 2)->default(0);
            $table->decimal('credit_limit', 10, 2)->default(1000);
            $table->integer('total_orders')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_company')->default(false);
            $table->integer('customer_rank')->default(0);
            $table->integer('supplier_rank')->default(0);
            $table->string('vat')->nullable();
            $table->string('website')->nullable();
            $table->text('comment')->nullable();
            $table->string('function')->nullable();
            $table->string('street')->nullable();
            $table->string('street2')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            // Index
            $table->index('user_type');
            $table->index('email');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};