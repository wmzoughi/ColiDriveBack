<?php
// app/Http/Controllers/Produits/ProductController.php

namespace App\Http\Controllers\Produits;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;  

class ProductController extends Controller
{
    /**
     * GET /api/products - Liste des produits
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Product::where('active', true)
            ->with(['supplier', 'category']);
        
        // Filtrer par fournisseur si l'utilisateur est un fournisseur
        if ($user->user_type === 'fournisseur') {
            $query->where('supplier_id', $user->id);
        }
        
        // Recherche textuelle
        if ($request->has('search')) {
            $search = strtolower($request->search);
            $query->whereRaw("LOWER(name->>'en_US') LIKE ? OR LOWER(name->>'fr_FR') LIKE ?", 
                ["%{$search}%", "%{$search}%"]);
        }
        
        // ✅ FILTRE PAR CATÉGORIE (AJOUTÉ)
        if ($request->has('categ_id') && $request->categ_id != '') {
            $query->where('categ_id', $request->categ_id);
        }
        
        // ✅ FILTRE PAR PROMOTION
        if ($request->has('in_promotion') && $request->in_promotion) {
            $now = now();
            $query->where('is_promotion', true)
                ->where('promotion_start', '<=', $now)
                ->where('promotion_end', '>=', $now);
        }
        
        // Tri
        $orderBy = $request->get('order_by', 'create_date');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        $products = $query->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * GET /api/products/{id} - Détail d'un produit
     */
    public function show($id)
    {
        try {
            $product = Product::with(['supplier', 'category'])
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }
    }

    /**
     * GET /api/products/supplier/{supplierId} - Produits d'un fournisseur
     */
    public function bySupplier($supplierId)
    {
        $supplier = User::where('user_type', 'fournisseur')
            ->where('id', $supplierId)
            ->first();

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        $products = Product::where('supplier_id', $supplierId)
            ->where('active', true)
            ->with('supplier', 'category')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * GET /api/categories - Liste des catégories
     */
    public function categories()
    {
        $categories = ProductCategory::where('active', true)
            ->select('id', 'name', 'complete_name', 'parent_id')
            ->with('children')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * POST /api/products - Ajouter un produit (fournisseur uniquement)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if ($user->user_type !== 'fournisseur') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les fournisseurs peuvent ajouter des produits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'list_price' => 'required|numeric|min:0',
            'packaging' => 'nullable|string|max:50',
            'is_promotion' => 'in:true,false,0,1',
            'promotion_price' => 'required_if:is_promotion,true|nullable|numeric|min:0',
            'promotion_start' => 'required_if:is_promotion,true|nullable|date',
            'promotion_end' => 'required_if:is_promotion,true|nullable|date|after:promotion_start',
            'categ_id' => 'required|integer|exists:product_categories,id',
            'image_url' => 'nullable|url',  // 👈 AJOUTER
            'image' => 'nullable|image|max:2048', // 👈 AJOUTER (fichier image)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $productData = [
                'supplier_id' => $user->id,
                'list_price' => $request->list_price,
                'packaging' => $request->packaging,
                'is_promotion' => $request->is_promotion ?? false,
                'active' => true,
                'type' => 'product',
                'detailed_type' => 'product',
                'uom_id' => 1,
                'uom_po_id' => 1,
                'categ_id' => $request->categ_id,
                'sale_line_warn' => 'no-message',
                'purchase_line_warn' => 'no-message',
                'sale_ok' => true,
                'purchase_ok' => true,
                'tracking' => 'none',
                'name' => $request->name,
            ];

            // 👇 GESTION DE L'IMAGE
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $productData['image_url'] = Storage::url($path);
            } elseif ($request->has('image_url')) {
                $productData['image_url'] = $request->image_url;
            }

            if ($request->has('description')) {
                $productData['description'] = $request->description;
            }

            if ($request->is_promotion) {
                $productData['promotion_price'] = $request->promotion_price;
                $productData['promotion_start'] = $request->promotion_start;
                $productData['promotion_end'] = $request->promotion_end;
            }

            $product = Product::create($productData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit ajouté avec succès',
                'data' => $product->load(['supplier', 'category'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du produit',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * POST /api/supplier/products/{id}/image
     * Uploader une image pour un produit spécifique
     */
    public function uploadImage(Request $request, $id)
    {
        $user = $request->user();
        
        try {
            $product = Product::findOrFail($id);
            
            if ($user->id !== $product->supplier_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // 👉 Vérification simple
            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune image fournie'
                ], 400);
            }

            $file = $request->file('image');
            
            // 👉 Stockage simple
            $path = $file->store('products', 'public');
            
            // 👉 Mise à jour du produit
            $product->update([
                'image_url' => Storage::url($path)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image téléchargée',
                'data' => ['image_url' => Storage::url($path)]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/products/{id} - Modifier un produit
     */
    public function update(Request $request, $id)   
    {
        $user = $request->user();
        
        try {
            $product = Product::findOrFail($id);
            
            if ($user->user_type !== 'fournisseur' || $product->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'list_price' => 'sometimes|numeric|min:0',
                'packaging' => 'nullable|string|max:50',
                'is_promotion' => 'boolean',
                'promotion_price' => 'required_if:is_promotion,true|nullable|numeric|min:0',
                'promotion_start' => 'required_if:is_promotion,true|nullable|date',
                'promotion_end' => 'required_if:is_promotion,true|nullable|date|after:promotion_start',
                'categ_id' => 'sometimes|integer|exists:product_categories,id',
                'active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only([
                'name', 'description', 'packaging', 'list_price',
                'is_promotion', 'promotion_price', 'promotion_start',
                'promotion_end', 'categ_id', 'active', 'popular_rank'
            ]);

            // Gérer le nom (JSONB)
            if ($request->has('name')) {
                $product->name = $request->name;
                unset($updateData['name']);
            }

            // Gérer la description (JSONB)
            if ($request->has('description')) {
                $product->description = $request->description;
                unset($updateData['description']);
            }

            $product->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produit modifié',
                'data' => $product->fresh(['supplier', 'category'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur de modification',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * DELETE /api/products/{id} - Désactiver un produit
     */
    public function destroy($id)
    {
        $user = request()->user();
        
        try {
            $product = Product::findOrFail($id);
            
            if ($user->user_type !== 'fournisseur' || $product->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $product->update(['active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Produit désactivé'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de suppression'
            ], 500);
        }
    }

    /**
     * POST /api/products/{id}/stock - Mettre à jour le stock
     */
    public function updateStock(Request $request, $id)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'operation' => 'required|in:add,remove,set',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($id);
            
            if ($user->user_type !== 'fournisseur' || $product->supplier_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            DB::beginTransaction();

            $oldQuantity = $product->stock_quantity ?? 0;
            
            switch ($request->operation) {
                case 'add':
                    $newQuantity = $oldQuantity + $request->quantity;
                    break;
                case 'remove':
                    $newQuantity = max(0, $oldQuantity - $request->quantity);
                    break;
                case 'set':
                    $newQuantity = max(0, $request->quantity);
                    break;
                default:
                    $newQuantity = $oldQuantity;
            }

            $product->update(['stock_quantity' => $newQuantity]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock mis à jour',
                'data' => [
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'product' => $product->load('supplier')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du stock',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * GET /api/supplier/products - Produits du fournisseur connecté
     */
    public function supplierProducts(Request $request)
    {
        $user = $request->user();
        
        $query = Product::where('supplier_id', $user->id)
            ->with('category');

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereRaw("LOWER(name->>'en_US') LIKE ? OR LOWER(name->>'fr_FR') LIKE ?", 
                ["%{$search}%", "%{$search}%"]);
        }

        // Filtre par catégorie
        if ($request->has('categ_id')) {
            $query->where('categ_id', $request->categ_id);
        }

        // En promotion
        if ($request->has('in_promotion') && $request->in_promotion) {
            $now = now();
            $query->where('is_promotion', true)
                ->where('promotion_start', '<=', $now)
                ->where('promotion_end', '>=', $now);
        }

        // 👉 CHANGE ICI : create_date -> created_at
        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}