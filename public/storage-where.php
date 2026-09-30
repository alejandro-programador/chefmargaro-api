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
foreach (glob($cacheDir.'/*.php') ?: [] as $cacheFile) {
    $cache[] = basename($cacheFile);
}

$out = [
    'root' => $root,
    'cache_php' => $cache,
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

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
