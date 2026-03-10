<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'payment_status',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'shipping_address',
        'shipping_city',
        'shipping_zip',
        'shipping_phone',
        'notes',
        'payment_method',
        'payment_details',
        'confirmed_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping_cost' => 'float',
        'total' => 'float',
        'payment_details' => 'array',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_DELIVERING = 'delivering';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID = 'paid';

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_PREPARING => 'En préparation',
            self::STATUS_DELIVERING => 'En livraison',
            self::STATUS_DELIVERED => 'Livrée',
            self::STATUS_CANCELLED => 'Annulée',
            default => $this->status,
        };
    }

    public function getPaymentStatusLabelAttribute()
    {
        return match($this->payment_status) {
            self::PAYMENT_UNPAID => 'Impayée',
            self::PAYMENT_PAID => 'Payée',
            default => $this->payment_status,
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'orange',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_PREPARING => 'purple',
            self::STATUS_DELIVERING => 'indigo',
            self::STATUS_DELIVERED => 'green',
            self::STATUS_CANCELLED => 'red',
            default => 'grey',
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Générer un numéro de commande unique
            $order->order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        });
    }
}