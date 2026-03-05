<?php
// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;  
use App\Http\Controllers\Produits\ProductController;
use App\Http\Controllers\Merchant\MerchantController;
use App\Http\Controllers\OrderController;        // 👈 À créer
use App\Http\Controllers\DashboardController;    // 👈 À créer
use App\Http\Controllers\Produits\ImageController;

// Routes publiques
Route::prefix('auth')->group(function () {
    Route::post('check-email', [AuthController::class, 'checkEmail']);
    Route::post('check-siret', [AuthController::class, 'checkSiret']);
    Route::post('register/commercant', [AuthController::class, 'registerCommercant']);
    Route::post('register/fournisseur', [AuthController::class, 'registerFournisseur']);

    Route::post('login', [AuthController::class, 'login']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
    
    // ===== CATÉGORIES =====
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/tree', [CategoryController::class, 'tree']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    
    // ===== PRODUITS =====
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products/supplier/{supplierId}', [ProductController::class, 'bySupplier']);
    
    // ===== FOURNISSEURS SEULEMENT =====
    Route::middleware('check.user.type:fournisseur')->prefix('supplier')->group(function () {
        // Produits
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/products/{id}/stock', [ProductController::class, 'updateStock']);
        Route::get('/products', [ProductController::class, 'supplierProducts']); // 👈 Produits du fournisseur
        Route::post('/products/{id}/image', [ProductController::class, 'uploadImage']);

        Route::get('/image/{filename}', [ImageController::class, 'show'])
        ->withoutMiddleware(['auth:sanctum'])
        ->where('filename', '.*');
        
        // Commandes
        Route::get('/orders', [OrderController::class, 'supplierOrders']);           // 👈 Liste des commandes
        Route::get('/orders/stats', [OrderController::class, 'supplierStats']);      // 👈 Statistiques
        Route::get('/orders/recent', [OrderController::class, 'recentOrders']);      // 👈 Commandes récentes
        
        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'supplierStats']); // 👈 Stats dashboard
        Route::get('/dashboard/chart', [DashboardController::class, 'salesChart']);    // 👈 Données graphique
    });
    
    // ===== COMMERÇANTS SEULEMENT =====
    Route::middleware('check.user.type:commercant')->prefix('merchant')->group(function () {
        // Fournisseurs
        Route::get('/suppliers/available', [AuthController::class, 'availableSuppliers']);
        Route::post('/suppliers/add', [AuthController::class, 'addSupplier']);
        
        // Crédit
        Route::get('/credit', [MerchantController::class, 'getCredit']);
        
        // Commandes
        Route::get('/orders', [OrderController::class, 'merchantOrders']);
        Route::get('/orders/stats', [OrderController::class, 'merchantStats']);
        Route::get('/orders/{id}', [OrderController::class, 'merchantOrderDetails']);
        
        // Dashboard (optionnel)
        Route::get('/dashboard/stats', [DashboardController::class, 'merchantStats']);
    });
});