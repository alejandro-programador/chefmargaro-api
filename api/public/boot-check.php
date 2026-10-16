<?php

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'chefmargaro-boot-2026') {
    http_response_code(404);
    echo 'Not found';
    exit;
}

echo 'php '.PHP_VERSION."\n";
echo 'autoload '.(is_file(__DIR__.'/../vendor/autoload.php') ? 'yes' : 'NO')."\n";

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    echo 'app '.get_class($app)."\n";
    echo 'handleRequest '.(method_exists($app, 'handleRequest') ? 'yes' : 'NO')."\n";
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    echo 'kernel '.get_class($kernel)."\n";
} catch (Throwable $e) {
    echo 'ERROR '.$e::class."\n";
    echo $e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
