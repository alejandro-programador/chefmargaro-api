<?php

use Illuminate\Support\Facades\Route;

/*
| Fallback para Hostinger (public_html): sirve archivos de storage/app/public
| cuando no hay symlink o Apache no puede seguirlo.
| URL: /storage/combos/imagen.jpg
*/
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], '', $path);
    $relative = ltrim($path, '/');
    if (str_starts_with($relative, 'app/public/')) {
        $relative = substr($relative, strlen('app/public/'));
    }

    $candidates = array_values(array_unique(array_filter([
        storage_path('app/public/'.$relative),
        storage_path('app/private/'.$relative),
        public_path('storage/'.$relative),
        public_path($relative),
        base_path('public/storage/'.$relative),
        dirname(base_path()).'/storage/'.$relative,
        dirname(base_path()).'/storage/app/public/'.$relative,
    ])));

    foreach ($candidates as $fullPath) {
        if (is_file($fullPath)) {
            return response()->file($fullPath, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }
    }

    abort(404);
})->where('path', '.*');

Route::get('/', function () {
    return view('welcome');
});
