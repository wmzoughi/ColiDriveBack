<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
  

    /**
     * POST /api/supplier/orders - Créer une commande (pour fournisseur)
     */

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:100',
            'shipping_zip' => 'required|string|max:20',
            'shipping_phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:500',
            'payment_method' => 'required|in:cash,card',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer le panier de l'utilisateur
        $cart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Panier vide'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // ✅ VÉRIFIER LE STOCK AVANT DE CRÉER LA COMMANDE
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                
                // Vérifier si le produit a un stock suffisant
                if ($product->stock_quantity < $cartItem->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant pour {$product->name}. Disponible: {$product->stock_quantity}, Demandé: {$cartItem->quantity}"
                    ], 400);
                }
            }

            // Calculer les totaux
            $subtotal = $cart->subtotal;
            $tax = $subtotal * 0.20; // TVA 20%
            $shippingCost = 50; // Frais de livraison fixes
            $total = $subtotal + $tax + $shippingCost;

            // Créer la commande
            $order = Order::create([
                'customer_id' => $user->id,
                'status' => Order::STATUS_PENDING,
                'payment_status' => $request->payment_method === 'card' ? Order::PAYMENT_PAID : Order::PAYMENT_UNPAID,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_zip' => $request->shipping_zip,
                'shipping_phone' => $request->shipping_phone,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
            ]);

            // Créer les articles de commande ET METTRE À JOUR LE STOCK
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->default_code ?? 'N/A',
                    'price' => $cartItem->price_at_time,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $cartItem->price_at_time * $cartItem->quantity,
                    'product_snapshot' => json_encode([
                        'name' => $product->name,
                        'description' => $product->description,
                        'image_url' => $product->image_url,
                        'packaging' => $product->packaging,
                        'supplier_id' => $product->supplier_id,
                        'supplier_name' => $product->supplier->company_name ?? $product->supplier->name,
                    ]),
                ]);

                // ✅ DÉCRÉMENTER LE STOCK
                $product->stock_quantity -= $cartItem->quantity;
                $product->save();
            }

            // Marquer le panier comme converti
            $cart->status = 'converted';
            $cart->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'order' => $order->load('items'),
                    'order_number' => $order->order_number,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/supplier/orders - Liste des commandes (pour fournisseur)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::with('items', 'customer')
            ->whereHas('items.product', function($q) use ($user) {
                $q->where('supplier_id', $user->id);
            });

        // Filtrer par statut
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/supplier/orders/{id} - Détail d'une commande (pour fournisseur)
     */
    public function show($id)
    {
        $user = request()->user();

        $order = Order::with('items', 'customer')
            ->whereHas('items.product', function($q) use ($user) {
                $q->where('supplier_id', $user->id);
            })
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * POST /api/supplier/orders/{id}/cancel - Annuler une commande (pour fournisseur)
     */
    // app/Http/Controllers/OrderController.php

    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Vérifier que la commande contient des produits de ce fournisseur
        $hasSupplierProducts = $order->items()->whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->exists();

        if (!$hasSupplierProducts) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne contient pas vos produits'
            ], 403);
        }

        if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // ✅ RESTAURER LE STOCK
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product) {
                    $product->stock_quantity += $item->quantity;
                    $product->save();
                }
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->cancelled_at = now();
            $order->cancellation_reason = $request->reason;
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée et stock restauré',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * POST /api/supplier/orders/{id}/confirm - Confirmer une commande (pour fournisseur)
     */
    public function confirm(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Vérifier que la commande contient des produits de ce fournisseur
        $hasSupplierProducts = $order->items()->whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->exists();

        if (!$hasSupplierProducts) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne contient pas vos produits'
            ], 403);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut pas être confirmée'
            ], 400);
        }

        $order->status = Order::STATUS_CONFIRMED;
        $order->confirmed_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Commande confirmée',
            'data' => $order
        ]);
    }

    // app/Http/Controllers/OrderController.php

    public function prepare(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Vérifier que la commande contient des produits de ce fournisseur
        $hasSupplierProducts = $order->items()->whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->exists();

        if (!$hasSupplierProducts) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne contient pas vos produits'
            ], 403);
        }

        if ($order->status !== Order::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut pas être mise en préparation'
            ], 400);
        }

        $order->status = Order::STATUS_PREPARING;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Commande en préparation',
            'data' => $order
        ]);
    }

    public function deliver(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Vérifier que la commande contient des produits de ce fournisseur
        $hasSupplierProducts = $order->items()->whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->exists();

        if (!$hasSupplierProducts) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne contient pas vos produits'
            ], 403);
        }

        if ($order->status !== Order::STATUS_PREPARING) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande doit d\'abord être en préparation'
            ], 400);
        }

        $order->status = Order::STATUS_DELIVERING;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Commande en cours de livraison',
            'data' => $order
        ]);
    }

    /**
     * GET /api/supplier/orders/stats - Statistiques des commandes (pour fournisseur)
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $totalOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->count();

        $pendingOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_PENDING)->count();

        $confirmedOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_CONFIRMED)->count();

        $preparingOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_PREPARING)->count();

        $deliveringOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_DELIVERING)->count();

        $deliveredOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_DELIVERED)->count();

        $cancelledOrders = Order::whereHas('items.product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->where('status', Order::STATUS_CANCELLED)->count();

        $totalRevenue = OrderItem::whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->whereHas('order', function($q) {
            $q->where('status', '!=', Order::STATUS_CANCELLED);
        })->sum(DB::raw('price * quantity'));

        $monthlyRevenue = OrderItem::whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->whereHas('order', function($q) {
            $q->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year)
              ->where('status', '!=', Order::STATUS_CANCELLED);
        })->sum(DB::raw('price * quantity'));

        $totalProductsSold = OrderItem::whereHas('product', function($q) use ($user) {
            $q->where('supplier_id', $user->id);
        })->whereHas('order', function($q) {
            $q->where('status', '!=', Order::STATUS_CANCELLED);
        })->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'confirmed_orders' => $confirmedOrders,
                'preparing_orders' => $preparingOrders,
                'delivering_orders' => $deliveringOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
                'products_sold' => $totalProductsSold,
            ]
        ]);
    }

    /**
     * GET /api/supplier/orders/recent - Commandes récentes (pour fournisseur)
     */
    public function recentOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with('items', 'customer')
            ->whereHas('items.product', function($q) use ($user) {
                $q->where('supplier_id', $user->id);
            })
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/merchant/orders - Liste des commandes du commerçant
     */
    public function merchantOrders(Request $request)
    {
        $user = $request->user();

        $query = Order::with('items')
            ->where('customer_id', $user->id);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * GET /api/merchant/orders/stats - Statistiques des commandes du commerçant
     */
    public function merchantStats(Request $request)
    {
        $user = $request->user();

        $totalOrders = Order::where('customer_id', $user->id)->count();
        $pendingOrders = Order::where('customer_id', $user->id)->where('status', Order::STATUS_PENDING)->count();
        $confirmedOrders = Order::where('customer_id', $user->id)->where('status', Order::STATUS_CONFIRMED)->count();
        $deliveredOrders = Order::where('customer_id', $user->id)->where('status', Order::STATUS_DELIVERED)->count();
        $cancelledOrders = Order::where('customer_id', $user->id)->where('status', Order::STATUS_CANCELLED)->count();
        
        $totalRevenue = Order::where('customer_id', $user->id)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->sum('total');

        $monthlyRevenue = Order::where('customer_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'confirmed_orders' => $confirmedOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => $monthlyRevenue,
            ]
        ]);
    }

    /**
     * GET /api/merchant/orders/{id} - Détail d'une commande du commerçant
     */
    public function merchantOrderDetails($id)
    {
        $user = request()->user();

        $order = Order::with('items')
            ->where('customer_id', $user->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * GET /api/orders/track/{orderNumber} - Suivre une commande sans authentification
     */
    public function trackOrder($orderNumber)
    {
        $order = Order::with('items')
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'created_at' => $order->created_at,
                'estimated_delivery' => $order->created_at->addDays(3),
                'items_count' => $order->items->count(),
                'total' => $order->total,
            ]
        ]);
    }
}