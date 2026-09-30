<?php

/**
 * Localiza imágenes subidas. Se elimina después de diagnosticar el 404.
 */
$root = dirname(__DIR__);
$file = 'HfiWu5gLOKNjOqN1Je5RdlMnBpors6tXnbbAErhE.png';
$dirs = [
    $root.'/storage/app/public',
    $root.'/storage/app/public/combos',
    $root.'/storage/app/public/extras',
    $root.'/storage/app/private',
    $root.'/storage/app/private/combos',
    $root.'/public/storage',
    $root.'/public/storage/combos',
    $root.'/public/combos',
    dirname($root).'/storage/app/public/combos',
    dirname($root).'/public_html/storage/combos',
];

$cacheDir = $root.'/bootstrap/cache';
$cache = [];
$removed = [];
foreach (glob($cacheDir.'/*.php') ?: [] as $cacheFile) {
    $base = basename($cacheFile);
    $cache[] = $base;
    if (str_starts_with($base, 'routes') || $base === 'config.php') {
        if (@unlink($cacheFile)) {
            $removed[] = $base;
        }
    }
}

$out = [
    'root' => $root,
    'cache_php' => $cache,
    'removed_route_cache' => $removed,
    'dirs' => [],
];

foreach ($dirs as $dir) {
    $entries = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : null;
    $out['dirs'][] = [
        'path' => $dir,
        'is_dir' => is_dir($dir),
        'count' => is_array($entries) ? count($entries) : 0,
        'has_file' => is_array($entries) && in_array($file, $entries, true),
        'sample' => is_array($entries) ? array_slice($entries, 0, 15) : [],
    ];
}

$web = $root.'/routes/web.php';
$out['web_php'] = [
    'exists' => is_file($web),
    'mtime' => is_file($web) ? date('c', filemtime($web)) : null,
    'has_storage_route' => is_file($web) && str_contains((string) file_get_contents($web), "storage/{path}"),
];
$comboFile = $root.'/storage/app/public/combos/'.$file;
$out['combo_file'] = [
    'path' => $comboFile,
    'is_file' => is_file($comboFile),
    'size' => is_file($comboFile) ? filesize($comboFile) : null,
];

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$uris = [];
foreach ($app->make('router')->getRoutes() as $route) {
    $uri = $route->uri();
    if (str_contains($uri, 'storage')) {
        $uris[] = $uri;
    }
}
$out['storage_routes'] = $uris;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
