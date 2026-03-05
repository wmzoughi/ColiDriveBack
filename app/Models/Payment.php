<?php
// app/Models/AccountPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayment extends Model
{
    use HasFactory;

    /**
     * Le nom de la table Odoo
     */
    protected $table = 'account_payment';

    /**
     * La clé primaire
     */
    protected $primaryKey = 'id';

    /**
     * Odoo utilise create_date et write_date
     */
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'write_date';

    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'name',
        'partner_id',
        'merchant_id',
        'payment_reference_colidrive',
        'payment_method_colidrive',
        'amount',
        'payment_type',
        'state',
        'payment_date',
        'communication',
        'journal_id',
        'company_id',
        'currency_id',
        'partner_type',
        'destination_account_id',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'datetime',
        'create_date' => 'datetime',
        'write_date' => 'datetime',
    ];

    /**
     * Relations
     */
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function move()
    {
        return $this->belongsTo(AccountMove::class, 'move_id');
    }
}