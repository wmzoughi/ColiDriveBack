<?php
// app/Models/SaleOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrder extends Model
{
    use HasFactory;

    protected $table = 'sale_orders'; // 👈 Maintenant 'sale_orders'
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_number',
        'partner_id',
        'supplier_id',
        'delivery_status',
        'state',
        'amount_total',
        'amount_tax',
        'amount_untaxed',
        'date_order',
        'validity_date',
        'user_id',
        'company_id',
        'invoice_status',
        'note',
        'client_order_ref',
        'origin',
    ];

    protected $casts = [
        'amount_total' => 'float',
        'amount_tax' => 'float',
        'amount_untaxed' => 'float',
        'date_order' => 'datetime',
        'validity_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function lines()
    {
        return $this->hasMany(SaleOrderLine::class, 'order_id');
    }

    public function invoices()
    {
        return $this->hasMany(AccountMove::class, 'order_id');
    }
}