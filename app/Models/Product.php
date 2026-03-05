<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products'; // 👈 Maintenant 'products'
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
        
        // Nettoyer les doubles slashes
        $clean = preg_replace('#/+#', '/', $value);
        
        // Si c'est déjà une URL complète
        if (str_starts_with($clean, 'http')) {
            return $clean;
        }
        
        // Si ça commence par /storage/, garder tel quel
        if (str_starts_with($clean, '/storage/')) {
            return $clean;
        }
        
        // Sinon, ajouter /storage/
        return '/storage/' . $clean;
    }
}