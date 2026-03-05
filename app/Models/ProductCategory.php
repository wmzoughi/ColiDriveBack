<?php
// app/Models/ProductCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories'; // 👈 Maintenant 'product_categories'
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'parent_id',
        'complete_name',
        'popular_rank',
        'image_url',
        'description',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'popular_rank' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'categ_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function scopePopular($query)
    {
        return $query->orderBy('popular_rank', 'desc');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}