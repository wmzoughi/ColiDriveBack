<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountMove extends Model
{
    use HasFactory;

    protected $table = 'account_move';

    protected $primaryKey = 'id';
    
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'write_date';

    protected $fillable = [
        'partner_id',
        'state',
        'payment_state',
        'amount_total_signed',
        // ... autres champs
    ];

    protected $casts = [
        'amount_total_signed' => 'float',
        'create_date' => 'datetime',
        'write_date' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}