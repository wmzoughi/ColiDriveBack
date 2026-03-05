<?php
// app/Http/Controllers/ImageController.php

namespace App\Http\Controllers\Produits;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function show($filename)
    {
        $path = storage_path('app/public/products/' . $filename);
        
        if (!file_exists($path)) {
            return response()->json(['error' => 'Image non trouvée'], 404);
        }
        
        $file = file_get_contents($path);
        $mime = mime_content_type($path);
        
        return response($file, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Length', filesize($path))
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
}