<?php
// app/Http/Controllers/Auth/AuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerCredit;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Inscription d'un commerçant
     * Un commerçant PEUT choisir un fournisseur plus tard
     */
    public function registerCommercant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'siret' => 'required|string|size:14|unique:users,siret',
            'phone' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
            'accept_terms' => 'accepted'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Créer le commerçant - SANS password pour l'instant
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 'commercant',
                'siret' => $request->siret,
                'phone' => $request->phone,
                'company_name' => $request->company_name,
                'active' => true,
                'customer_rank' => 1,
                'supplier_rank' => 0,
            ]);

      

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Commerçant inscrit avec succès',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Inscription d'un fournisseur
     */
    public function registerFournisseur(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'accept_terms' => 'accepted'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // Créer le fournisseur - SANS password pour l'instant
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 'fournisseur',
                'phone' => $request->phone,
                'company_name' => $request->company_name,
                'active' => true,
                'customer_rank' => 0,
                'supplier_rank' => 1,
            ]);

        

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Fournisseur inscrit avec succès',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Connexion (commun aux deux types)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'success' => false,
                'message' => 'Compte désactivé'
            ], 403);
        }

        // 🔥 FUSIONNER LE PANIER DE LA SESSION AVEC LE PANIER DE L'UTILISATEUR
        $sessionId = $request->header('X-Session-ID') ?? $request->cookie('cart_session');
        
        if ($sessionId) {
            // Chercher un panier de session
            $sessionCart = Cart::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();
            
            // Chercher le panier de l'utilisateur
            $userCart = Cart::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();
            
            if ($sessionCart && $sessionCart->items->isNotEmpty()) {
                if ($userCart) {
                    // Fusionner les deux paniers
                    foreach ($sessionCart->items as $sessionItem) {
                        $existingItem = $userCart->items()
                            ->where('product_id', $sessionItem->product_id)
                            ->first();
                        
                        if ($existingItem) {
                            // Ajouter les quantités
                            $existingItem->quantity += $sessionItem->quantity;
                            $existingItem->save();
                        } else {
                            // Transférer l'item au panier utilisateur
                            $sessionItem->cart_id = $userCart->id;
                            $sessionItem->save();
                        }
                    }
                    // Supprimer l'ancien panier de session
                    $sessionCart->delete();
                } else {
                    // Assigner le panier de session à l'utilisateur
                    $sessionCart->user_id = $user->id;
                    $sessionCart->session_id = null;
                    $sessionCart->save();
                }
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Charger le panier final
        $finalCart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => $this->formatUserData($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'cart' => $finalCart ? [
                    'id' => $finalCart->id,
                    'items' => $finalCart->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'product' => $item->product,
                            'price' => $item->price_at_time,
                            'subtotal' => $item->price_at_time * $item->quantity,
                        ];
                    })->values(),
                    'total_items' => $finalCart->total_items,
                    'subtotal' => $finalCart->subtotal,
                ] : [
                    'id' => null,
                    'items' => [],
                    'total_items' => 0,
                    'subtotal' => 0,
                ],
            ]
        ]);
    }

    
    /**
     * Ajouter un fournisseur pour un commerçant (après inscription)
     */
    public function addSupplier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        if ($user->user_type !== 'commercant') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les commerçants peuvent ajouter des fournisseurs'
            ], 403);
        }

        // Vérifier si un crédit existe déjà pour ce fournisseur
        $existingCredit = CustomerCredit::where('partner_id', $user->id)
            ->where('supplier_id', $request->supplier_id)
            ->first();

        if (!$existingCredit) {
            // Créer le crédit pour ce couple (commerçant, fournisseur)
            CustomerCredit::create([
                'partner_id' => $user->id,
                'supplier_id' => $request->supplier_id,
                'credit_limit' => 1000.00, // Limite par défaut
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fournisseur ajouté avec succès'
        ]);
    }

    /**
     * Liste des fournisseurs disponibles pour un commerçant
     */
    public function availableSuppliers()
    {
        $suppliers = User::where('user_type', 'fournisseur')
            ->where('active', true)
            ->select('id', 'name', 'company_name', 'phone', 'email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $suppliers
        ]);
    }

    /**
     * Formatage des données utilisateur
     */
    private function formatUserData($user)
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'is_active' => $user->active,
            'created_at' => $user->create_date?->format('Y-m-d H:i:s'),
        ];

        if ($user->user_type === 'commercant') {
            $data['siret'] = $user->siret;
            
            // Charger les crédits avec les fournisseurs
            $credits = $user->customerCredits()->with('supplier')->get();
            
            $data['suppliers'] = $credits->map(function($credit) {
                return [
                    'id' => $credit->supplier->id,
                    'name' => $credit->supplier->name,
                    'company_name' => $credit->supplier->company_name,
                    'credit_limit' => $credit->credit_limit,
                    'current_credit' => $credit->current_credit,
                    'available_credit' => $credit->available_credit,
                ];
            });
        }

        if ($user->user_type === 'fournisseur') {
            $data['stats'] = [
                'products_count' => $user->products()->count(),
                'clients_count' => $user->supplierCredits()->count(),
            ];
        }

        return $data;
    }

    // Vérifications d'unicité
    public function checkEmail(Request $request)
    {
        $exists = User::where('email', $request->email)->exists();
        return response()->json([
            'success' => true,
            'data' => ['available' => !$exists]
        ]);
    }

    public function checkSiret(Request $request)
    {
        $exists = User::where('siret', $request->siret)->exists();
        return response()->json([
            'success' => true,
            'data' => ['available' => !$exists]
        ]);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Déconnecté']);
    }

    // Profil
    public function me(Request $request)
    {
        $user = $request->user();
        
        if ($user->user_type === 'commercant') {
            $user->load('customerCredits.supplier');
        }
        
        return response()->json([
            'success' => true,
            'data' => $this->formatUserData($user)
        ]);
    }
}