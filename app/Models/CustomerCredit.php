<?php
// app/Models/CustomerCredit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCredit extends Model
{
    use HasFactory;

    protected $table = 'customer_credits'; // 👈 Maintenant 'customer_credits'
    protected $primaryKey = 'id';

    protected $fillable = [
        'partner_id',
        'supplier_id',
        'credit_limit',
    ];

    protected $casts = [
        'credit_limit' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['current_credit'];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    /**
     * Calculer le crédit actuel (somme des factures impayées)
     */
    public function getCurrentCreditAttribute()
    {
        $unpaidInvoices = AccountMove::where('partner_id', $this->partner_id)
            ->where('state', 'posted')
            ->where('payment_state', '!=', 'paid')
            ->sum('amount_total_signed');

        return $unpaidInvoices ?? 0;
    }
}