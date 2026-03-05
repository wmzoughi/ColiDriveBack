<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Le nom de la table
     */
    protected $table = 'users'; 

    /**
     * La clé primaire
     */
    protected $primaryKey = 'id';

    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'siret',
        'phone',
        'company_name',
        'credit_balance',
        'credit_limit',
        'total_orders',
        'active',
        'is_company',
        'customer_rank',
        'supplier_rank',
        'vat',
        'website',
        'comment',
        'function',
        'street',
        'street2',
        'zip',
        'city',
     
    ];

    /**
     * Les attributs qui doivent être cachés.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'credit_balance' => 'float',
        'credit_limit' => 'float',
        'active' => 'boolean',
        'is_company' => 'boolean',
        'customer_rank' => 'integer',
        'supplier_rank' => 'integer',
        'total_orders' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    public function ordersAsCustomer()
    {
        return $this->hasMany(SaleOrder::class, 'partner_id');
    }

    public function ordersAsSupplier()
    {
        return $this->hasMany(SaleOrder::class, 'supplier_id');
    }

    public function customerCredits()
    {
        return $this->hasMany(CustomerCredit::class, 'partner_id');
    }

    public function supplierCredits()
    {
        return $this->hasMany(CustomerCredit::class, 'supplier_id');
    }

    public function invoicesAsPartner()
    {
        return $this->hasMany(AccountMove::class, 'partner_id');
    }

    public function paymentsAsMerchant()
    {
        return $this->hasMany(AccountPayment::class, 'merchant_id');
    }

    // 👇 SUPPRIME OU COMMENTE LES RELATIONS AVEC COUNTRY/STATE
    // public function country()
    // {
    //     return $this->belongsTo(Country::class);
    // }
    // 
    // public function state()
    // {
    //     return $this->belongsTo(State::class);
    // }

    /**
     * Scopes
     */
    public function scopeCommercants($query)
    {
        return $query->where('user_type', 'commercant');
    }

    public function scopeFournisseurs($query)
    {
        return $query->where('user_type', 'fournisseur');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}