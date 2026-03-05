<?php
// app/Models/SaleOrderLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderLine extends Model
{
    use HasFactory;

    /**
     * Le nom de la table Odoo
     */
    protected $table = 'sale_order_line';

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
        'order_id',
        'product_id',
        'name',
        'product_uom_qty',
        'price_unit',
        'price_subtotal',
        'price_total',
        'price_tax',
        'packaging',
        'promotion_applied',
        'product_uom',
        'discount',
        'state',
        'customer_lead',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'product_uom_qty' => 'float',
        'price_unit' => 'float',
        'price_subtotal' => 'float',
        'price_total' => 'float',
        'price_tax' => 'float',
        'discount' => 'float',
        'promotion_applied' => 'boolean',
        'create_date' => 'datetime',
        'write_date' => 'datetime',
    ];

    /**
     * Relations
     */
    public function order()
    {
        return $this->belongsTo(SaleOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Bootstrap du modèle
     */
    protected static function booted()
    {
        static::creating(function ($line) {
            // Calculer le prix avec les promos si nécessaire
            if ($line->product && $line->product->is_promotion) {
                $now = now();
                if ($line->product->promotion_start <= $now && 
                    $line->product->promotion_end >= $now) {
                    $line->price_unit = $line->product->promotion_price;
                    $line->promotion_applied = true;
                }
            }
        });
    }
}