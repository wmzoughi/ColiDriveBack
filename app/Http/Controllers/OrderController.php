<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/supplier/orders - Liste des commandes du fournisseur
     */
    public function supplierOrders(Request $request)
    {
        $user = $request->user();
        
        $query = Order::where('supplier_id', $user->id)
            ->with(['partner', 'lines.product']);

        // Filtres
        if ($request->has('status')) {
            $query->where('delivery_status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->where('create_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('create_date', '<=', $request->to_date);
        }

        $orders = $query->orderBy('create_date', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/supplier/orders/stats - Statistiques des commandes
     */
    public function supplierStats(Request $request)
    {
        $user = $request->user();
        
        $stats = [
            'total' => Order::where('supplier_id', $user->id)->count(),
            'pending' => Order::where('supplier_id', $user->id)
                ->whereIn('delivery_status', ['pending', 'confirmed'])
                ->count(),
            'delivered' => Order::where('supplier_id', $user->id)
                ->where('delivery_status', 'delivered')
                ->count(),
            'cancelled' => Order::where('supplier_id', $user->id)
                ->where('delivery_status', 'cancelled')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * GET /api/supplier/orders/recent - Commandes récentes
     */
    public function recentOrders(Request $request)
    {
        $user = $request->user();
        
        $orders = Order::where('supplier_id', $user->id)
            ->with('partner')
            ->orderBy('create_date', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/merchant/orders - Commandes du commerçant
     */
    public function merchantOrders(Request $request)
    {
        $user = $request->user();
        
        $query = Order::where('partner_id', $user->id)
            ->with(['supplier', 'lines.product']);

        if ($request->has('status')) {
            $query->where('delivery_status', $request->status);
        }

        $orders = $query->orderBy('create_date', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/merchant/orders/stats - Statistiques des commandes
     */
    public function merchantStats(Request $request)
    {
        $user = $request->user();
        
        $stats = [
            'total' => Order::where('partner_id', $user->id)->count(),
            'pending' => Order::where('partner_id', $user->id)
                ->whereIn('delivery_status', ['pending', 'confirmed'])
                ->count(),
            'delivered' => Order::where('partner_id', $user->id)
                ->where('delivery_status', 'delivered')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * GET /api/merchant/orders/{id} - Détail d'une commande
     */
    public function merchantOrderDetails($id)
    {
        try {
            $order = Order::with(['supplier', 'lines.product', 'partner'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }
    }
}