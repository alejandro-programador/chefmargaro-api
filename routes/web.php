<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
| Fallback para Hostinger (public_html): sirve archivos de storage/app/public
| cuando no hay symlink o Apache no puede seguirlo.
| URL: /storage/combos/imagen.jpg
*/
Route::get('/storage/{path}', function (string $path): BinaryFileResponse {
    $path = str_replace(['..', '\\'], '', $path);
    $fullPath = storage_path('app/public/'.$path);

    if (! is_file($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
    ]);
})->where('path', '.*');

Route::get('/', function () {
    return view('welcome');
});
