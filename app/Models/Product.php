<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'description',
        'supplier_id',
        'packaging',
        'is_promotion',
        'promotion_price',
        'promotion_start',
        'promotion_end',
        'popular_rank',
        'list_price',
        'default_code',
        'type',
        'detailed_type',
        'categ_id',
        'uom_id',
        'uom_po_id',
        'volume',
        'weight',
        'active',
        'sale_line_warn',
        'purchase_line_warn',
        'sale_ok',
        'purchase_ok',
        'tracking',
        'image_url',
        // 👇 AJOUTEZ CES CHAMPS
        'stock_quantity',
        'min_stock_alert',
        'max_stock_alert',
    ];

    protected $casts = [
        'list_price' => 'float',
        'promotion_price' => 'float',
        'volume' => 'float',
        'weight' => 'float',
        'is_promotion' => 'boolean',
        'active' => 'boolean',
        'promotion_start' => 'datetime',
        'promotion_end' => 'datetime',
        'popular_rank' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // 👇 AJOUTEZ CES CASTS
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
        'max_stock_alert' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'categ_id');
    }
    
    // Accesseur pour l'URL complète de l'image
    public function getImageUrlAttribute($value)
    {
        if (!$value) return null;
        
        $clean = preg_replace('#/+#', '/', $value);
        
        if (str_starts_with($clean, 'http')) {
            return $clean;
        }
        
        if (str_starts_with($clean, '/storage/')) {
            return $clean;
        }
        
        return '/storage/' . $clean;
    }

    // 👇 AJOUTEZ CES ACCESSEURS POUR LE STATUT DU STOCK
    public function getStockStatusAttribute()
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }
        if ($this->stock_quantity <= $this->min_stock_alert) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function getStockStatusLabelAttribute()
    {
        return match($this->stock_status) {
            'out_of_stock' => 'Rupture de stock',
            'low_stock' => 'Stock faible',
            'in_stock' => 'En stock',
            default => 'Inconnu',
        };
    }

    public function getStockStatusColorAttribute()
    {
        return match($this->stock_status) {
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'in_stock' => 'success',
            default => 'secondary',
        };
    }

    public function getIsInStockAttribute()
    {
        return $this->stock_quantity > 0;
    }

    public function getIsLowStockAttribute()
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->min_stock_alert;
    }
}