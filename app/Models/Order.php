<?php
// app/Models/SaleOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrder extends Model
{
    use HasFactory;

    /**
     * Le nom de la table Odoo
     */
    protected $table = 'sale_order';

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
        'pricelist_id',
        'payment_term_id',
        'fiscal_position_id',
        'invoice_status',
        'note',
        'client_order_ref',
        'origin',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'amount_total' => 'float',
        'amount_tax' => 'float',
        'amount_untaxed' => 'float',
        'date_order' => 'datetime',
        'validity_date' => 'date',
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
        return $this->hasMany(AccountMove::class, 'invoice_origin', 'name');
    }

    public function notifications()
    {
        return $this->morphMany(ColidriveNotification::class, 'reference', 'reference_model', 'reference_id');
    }

    /**
     * Méthodes
     */
    public function confirm()
    {
        $this->state = 'sale';
        $this->delivery_status = 'confirmed';
        $this->save();

        // Créer une notification pour le fournisseur
        ColidriveNotification::create([
            'user_id' => $this->supplier_id,
            'title' => "Nouvelle commande {$this->name}",
            'message' => "Commande de {$this->partner->name} d'un montant de {$this->amount_total}",
            'type' => 'order',
            'reference_id' => $this->id,
            'reference_model' => 'sale.order',
            'action_link' => "/web#id={$this->id}&model=sale.order"
        ]);

        return $this;
    }
}