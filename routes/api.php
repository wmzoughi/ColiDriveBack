<?php
// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;  
use App\Http\Controllers\Produits\ProductController;
use App\Http\Controllers\Merchant\MerchantController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Produits\ImageController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================== ROUTES PUBLIQUES ====================

// Authentification publique
Route::prefix('auth')->group(function () {
    Route::post('check-email', [AuthController::class, 'checkEmail']);
    Route::post('check-siret', [AuthController::class, 'checkSiret']);
    Route::post('register/commercant', [AuthController::class, 'registerCommercant']);
    Route::post('register/fournisseur', [AuthController::class, 'registerFournisseur']);
    Route::post('login', [AuthController::class, 'login']);
});

// Suivi de commande public
Route::get('/orders/track/{orderNumber}', [OrderController::class, 'trackOrder']);

// ==================== ROUTES PROTÉGÉES (AUTH SANCTUM) ====================
Route::middleware('auth:sanctum')->group(function () {
    
    // ===== AUTH =====
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // ===== PANIER =====
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('/items/{id}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
        Route::post('/convert', [CartController::class, 'convertToOrder']);
    });
    
    // ===== CATÉGORIES =====
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    
    // ===== PRODUITS (PUBLIC) =====
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products/supplier/{supplierId}', [ProductController::class, 'bySupplier']);

    
    
    // ==================== ROUTES FOURNISSEURS ====================
    Route::middleware('check.user.type:fournisseur')->prefix('supplier')->group(function () {
        
        // ---- Gestion des produits ----
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/products/{id}/stock', [ProductController::class, 'updateStock']);
        Route::get('/products', [ProductController::class, 'supplierProducts']);
        Route::post('/products/{id}/image', [ProductController::class, 'uploadImage']);

        // ---- Images (publique) ----
        Route::get('/image/{filename}', [ImageController::class, 'show'])
            ->withoutMiddleware(['auth:sanctum', 'check.user.type:fournisseur'])
            ->where('filename', '.*');
        
        // ---- Commandes fournisseur ----
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/stats', [OrderController::class, 'stats']);
        Route::get('/orders/recent', [OrderController::class, 'recentOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{id}/confirm', [OrderController::class, 'confirm']);
        Route::post('/orders/{id}/prepare', [OrderController::class, 'prepare']);
        Route::post('/orders/{id}/deliver', [OrderController::class, 'deliver']);
    });
    
    // ==================== ROUTES COMMERÇANTS ====================
    Route::middleware('check.user.type:commercant')->prefix('merchant')->group(function () {
        
        // ---- Fournisseurs ----
        Route::get('/suppliers/available', [AuthController::class, 'availableSuppliers']);
        Route::post('/suppliers/add', [AuthController::class, 'addSupplier']);
        
        // ---- Crédit ----
        Route::get('/credit', [MerchantController::class, 'getCredit']);
        
        // ---- Commandes commerçant ----
        Route::get('/orders', [OrderController::class, 'merchantOrders']);
        Route::post('/orders', [OrderController::class, 'store']);           // CRÉER UNE COMMANDE
        Route::get('/orders/stats', [OrderController::class, 'merchantStats']);
        Route::get('/orders/recent', [OrderController::class, 'recentOrders']); // COMMANDES RÉCENTES
        Route::get('/orders/{id}', [OrderController::class, 'merchantOrderDetails']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']); // ANNULER UNE COMMANDE
        
        // ---- Dashboard ----
        Route::get('/dashboard/stats', [DashboardController::class, 'merchantStats']);
    });
});