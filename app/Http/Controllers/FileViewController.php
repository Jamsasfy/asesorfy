<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileViewController extends Controller
{
    public function show(string $path): Response
    {
        // Comprueba si el archivo existe en tu disco público
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // Obtiene la ruta completa al archivo
        $fullPath = Storage::disk('public')->path($path);

        // Devuelve el archivo. response()->file() se encarga de las cabeceras correctas
        // para que se muestre en el navegador en lugar de descargarse.
        return response()->file($fullPath);
    }
}