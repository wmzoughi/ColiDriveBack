<?php
// app/Http/Controllers/MerchantController.php

namespace App\Http\Controllers\Merchant;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\CustomerCredit;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('check.user.type:commercant');
    }

    /**
     * GET /api/merchant/credit - Informations de crédit du commerçant
     */
    public function getCredit(Request $request)
    {
        $user = $request->user();
        
        // Récupérer le crédit du commerçant
        $credit = CustomerCredit::where('partner_id', $user->id)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $credit->current_credit ?? 0,
                'limit' => $credit->credit_limit ?? 1000,
                'available' => ($credit->credit_limit ?? 1000) - ($credit->current_credit ?? 0),
            ]
        ]);
    }
}