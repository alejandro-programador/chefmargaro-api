<?php

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'chefmargaro-boot-2026') {
    http_response_code(404);
    echo 'Not found';
    exit;
}

echo 'php '.PHP_VERSION."\n";
$autoload = __DIR__.'/../vendor/autoload.php';
$zipPath = __DIR__.'/../vendor-restore.zip';
echo 'autoload '.(is_file($autoload) ? 'yes' : 'NO')."\n";
echo 'zip '.(is_file($zipPath) ? 'yes' : 'NO')."\n";
echo 'ziparchive '.(class_exists('ZipArchive') ? 'yes' : 'NO')."\n";

if (! is_file($autoload) && is_file($zipPath) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    echo 'zip open '.var_export($opened, true)."\n";
    if ($opened === true) {
        $ok = $zip->extractTo(dirname(__DIR__));
        $zip->close();
        echo 'extracted '.var_export($ok, true)."\n";
    }
}
echo 'autoload after '.(is_file($autoload) ? 'yes' : 'NO')."\n";
echo 'disabled '.ini_get('disable_functions')."\n";

$which = static function (string $command): string {
    if (! function_exists('shell_exec')) {
        return 'shell_exec disabled';
    }
    $out = shell_exec($command.' 2>&1');

    return trim((string) $out);
};

echo "composer: ".$which('command -v composer || command -v composer.phar || true')."\n";
echo "ls parent:\n".$which('ls -la '.__DIR__.'/..')."\n";

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
