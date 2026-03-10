<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CartController extends Controller
{


    /**
     * GET /api/cart - Récupérer le panier de l'utilisateur connecté
     */
    public function getCart(Request $request)
    {
        $user = $request->user(); // L'utilisateur est automatiquement connecté grâce au middleware
        
        // Chercher le panier de l'utilisateur connecté UNIQUEMENT
        $cart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        
        // Si pas de panier, en créer un pour cet utilisateur
        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $user->id,
                'status' => 'active'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'subtotal' => $cart->subtotal,
                'total_items' => $cart->total_items,
                'total_products' => $cart->total_products,
            ]
        ]);
    }

    /**
     * POST /api/cart/items - Ajouter un produit au panier
     */
    public function addItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        // Récupérer ou créer le panier de l'utilisateur
        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            ['status' => 'active']
        );

        $product = Product::find($request->product_id);
        
        // Vérifier si le produit est déjà dans le panier
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            // Mettre à jour la quantité
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            // Ajouter un nouvel item
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price_at_time' => $product->current_price ?? $product->list_price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté au panier',
            'data' => [
                'cart_item' => $cartItem->load('product'),
                'cart' => $cart->load('items.product'),
            ]
        ]);
    }


    public function updateItem(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cartItem = CartItem::find($id);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé'
            ], 404);
        }

        $user = $request->user();
        $cart = $cartItem->cart;
        
        if ($user && $cart->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        if ($request->quantity <= 0) {
            $cartItem->delete();
            $message = 'Article retiré du panier';
        } else {
            $cartItem->quantity = $request->quantity;
            $cartItem->save();
            $message = 'Quantité mise à jour';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'cart' => $cart->fresh('items.product')
            ]
        ]);
    }

    public function removeItem($id)
    {
        $cartItem = CartItem::find($id);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé'
            ], 404);
        }

        $user = request()->user();
        $cart = $cartItem->cart;
        
        if ($user && $cart->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article retiré du panier',
            'data' => [
                'cart' => $cart->fresh('items.product')
            ]
        ]);
    }

    public function clearCart(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->header('X-Session-ID') ?? $request->cookie('cart_session');
        
        $cart = null;
        
        if ($user) {
            $cart = Cart::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
        } else if ($sessionId) {
            $cart = Cart::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();
        }

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Panier vidé'
        ]);
    }

    public function convertToOrder(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être connecté pour passer commande'
            ], 401);
        }

        $cart = Cart::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('items.product')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Panier vide'
            ], 400);
        }

        $cart->status = 'converted';
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Panier converti en commande',
            'data' => [
                'cart' => $cart
            ]
        ]);
    }
}