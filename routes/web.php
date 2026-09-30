<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    if (request()->boolean('probe')) {
        $dirs = [
            storage_path('app/public'),
            storage_path('app/public/combos'),
            storage_path('app/public/extras'),
            storage_path('app/private'),
            public_path('storage'),
            public_path('storage/combos'),
        ];

        return response()->json([
            'relative' => $relative,
            'base_path' => base_path(),
            'storage_path' => storage_path(),
            'public_path' => public_path(),
            'candidates' => collect($candidates)->map(fn ($full) => [
                'path' => $full,
                'is_file' => is_file($full),
                'is_readable' => is_readable($full),
            ])->all(),
            'dirs' => collect($dirs)->map(function ($dir) use ($relative) {
                $entries = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];

                return [
                    'path' => $dir,
                    'is_dir' => is_dir($dir),
                    'count' => count($entries),
                    'has_file' => in_array(basename($relative), $entries, true),
                    'sample' => array_slice($entries, 0, 8),
                ];
            })->all(),
        ]);
    }

    abort(404);
})->where('path', '.*');

Route::get('/', function () {
    return view('welcome');
});
