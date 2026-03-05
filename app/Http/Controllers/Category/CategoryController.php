<?php

namespace App\Http\Controllers\Category;
use App\Http\Controllers\Controller; 
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
  

    /**
     * GET /api/categories - Liste toutes les catégories
     */
    public function index(Request $request)
    {
         $query = ProductCategory::query();

        // Recherche par nom
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Catégories racines uniquement
        if ($request->has('root_only') && $request->root_only) {
            $query->whereNull('parent_id');
        }

        $categories = $query->with('children')->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * GET /api/categories/{id} - Détail d'une catégorie
     */
    public function show($id)
    {
        try {
            $category = ProductCategory::with(['parent', 'children'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }
    }

    /**
     * POST /api/categories - Ajouter une catégorie
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if ($user->user_type !== 'fournisseur') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les fournisseurs peuvent ajouter des catégories'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:product_category,id',
            'description' => 'nullable|string',
            'popular_rank' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'image_url' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Construire le nom complet
            $completeName = $request->name;
            if ($request->parent_id) {
                $parent = ProductCategory::find($request->parent_id);
                $completeName = $parent->complete_name . ' / ' . $request->name;
            }

            $categoryData = [
                'name' => $request->name,
                'parent_id' => $request->parent_id,
                'complete_name' => $completeName,
            ];

            // Ajouter les champs optionnels s'ils sont fournis
            if ($request->has('description')) {
                $categoryData['description'] = $request->description;
            }
            
            if ($request->has('popular_rank')) {
                $categoryData['popular_rank'] = $request->popular_rank;
            }
            
            if ($request->has('is_featured')) {
                $categoryData['is_featured'] = $request->is_featured;
            }
            
            if ($request->has('image_url')) {
                $categoryData['image_url'] = $request->image_url;
            }

            $category = ProductCategory::create($categoryData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie ajoutée avec succès',
                'data' => $category->load('parent', 'children')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * PUT /api/categories/{id} - Modifier une catégorie
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        try {
            $category = ProductCategory::findOrFail($id);

            // Vérifier les permissions
            if ($user->user_type !== 'fournisseur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'parent_id' => 'nullable|integer|exists:product_category,id',
        
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only(['name', 'parent_id']);

            // Recalculer complete_name si le nom ou le parent change
            if ($request->has('name') || $request->has('parent_id')) {
                $newName = $request->name ?? $category->name;
                $newParentId = $request->parent_id ?? $category->parent_id;
                
                if ($newParentId) {
                    $parent = ProductCategory::find($newParentId);
                    $updateData['complete_name'] = $parent->complete_name . ' / ' . $newName;
                } else {
                    $updateData['complete_name'] = $newName;
                }
            }

            $category->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie modifiée',
                'data' => $category->fresh(['parent', 'children'])
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
     * DELETE /api/categories/{id} - Supprimer une catégorie
     */
    public function destroy($id)
    {
        $user = request()->user();

        try {
            $category = ProductCategory::findOrFail($id);

            // Vérifier les permissions
            if ($user->user_type !== 'fournisseur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // Vérifier si la catégorie a des produits
            if ($category->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une catégorie qui contient des produits'
                ], 400);
            }

            // Vérifier si la catégorie a des sous-catégories
            if ($category->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une catégorie qui a des sous-catégories'
                ], 400);
            }

            // SUPPRESSION RÉELLE (pas de désactivation)
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de suppression'
            ], 500);
        }
    }
    /**
     * GET /api/categories/tree - Arbre des catégories
     */
    public function tree()
    {
        $categories = ProductCategory::whereNull('parent_id') 
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}