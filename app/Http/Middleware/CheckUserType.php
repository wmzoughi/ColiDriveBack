<?php
// app/Http/Middleware/CheckUserType.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserType
{
    /**
     * Vérifier le type d'utilisateur
     */
    public function handle(Request $request, Closure $next, ...$types)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        if (!in_array($request->user()->user_type, $types)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour ce type d\'utilisateur'
            ], 403);
        }

        return $next($request);
    }
}