<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/supplier/dashboard/stats - Statistiques pour fournisseur
     */
    public function supplierStats(Request $request)
    {
        $user = $request->user();
        
        // Commandes du fournisseur
        $orders = Order::where('supplier_id', $user->id)->get();
        
        // Produits du fournisseur
        $products = Product::where('supplier_id', $user->id)->get();
        
        // Statistiques
        $totalOrders = $orders->count();
        $pendingOrders = $orders->whereIn('status', ['pending', 'confirmed'])->count();
        $totalSales = $orders->sum('amount_total');
        
        // Produits en rupture (stock < 5)
        $outOfStockProducts = $products->filter(function($product) {
            return ($product->stock_quantity ?? 0) <= 5;
        })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_sales' => $totalSales,
                'out_of_stock_products' => $outOfStockProducts,
            ]
        ]);
    }

    /**
     * GET /api/supplier/dashboard/chart - Données du graphique des ventes
     */
    public function salesChart(Request $request)
    {
        $user = $request->user();
        
        // Récupérer les ventes des 6 derniers mois
        $sixMonthsAgo = now()->subMonths(6);
        
        $sales = Order::where('supplier_id', $user->id)
            ->where('create_date', '>=', $sixMonthsAgo)
            ->select(
                DB::raw('EXTRACT(MONTH FROM create_date) as month'),
                DB::raw('SUM(amount_total) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = ['Janv.', 'Fév.', 'Mars.', 'Avril', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
        
        $chartData = [];
        foreach ($sales as $sale) {
            $chartData[] = [
                'month' => $months[$sale->month - 1],
                'value' => $sale->total,
                'isActive' => $sale->month == now()->month,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }
}