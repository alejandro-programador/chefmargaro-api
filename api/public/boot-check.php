<?php

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'chefmargaro-boot-2026') {
    http_response_code(404);
    echo 'Not found';
    exit;
}

if (($_GET['log'] ?? '') === '1') {
    $log = __DIR__.'/../storage/logs/laravel.log';
    if (! is_file($log)) {
        echo 'no log';
        exit;
    }
    $text = file_get_contents($log);
    $pos = strrpos($text, 'production.ERROR');
    echo $pos === false ? substr($text, -4000) : substr($text, $pos, 2500);
    exit;
}

echo 'php '.PHP_VERSION."\n";
$autoload = __DIR__.'/../vendor/autoload.php';
$zipPath = __DIR__.'/../vendor-restore.zip';
echo 'autoload '.(is_file($autoload) ? 'yes' : 'NO')."\n";
echo 'zip '.(is_file($zipPath) ? 'yes' : 'NO')."\n";
echo 'ziparchive '.(class_exists('ZipArchive') ? 'yes' : 'NO')."\n";
echo 'curl '.(function_exists('curl_init') ? 'yes' : 'NO')."\n";
echo 'fopen wrappers '.(ini_get('allow_url_fopen') ? 'yes' : 'NO')."\n";

if (! is_file($zipPath)) {
    $url = 'https://github.com/alejandro-programador/chefmargaro-api/raw/861a463ee40a18fa5912d6f8679ae6717ceff705/api/vendor-restore.zip';
    $downloaded = false;
    if (function_exists('curl_init')) {
        $fp = fopen($zipPath, 'w');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'chefmargaro-restore',
        ]);
        $downloaded = curl_exec($ch) === true && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        echo 'curl http '.curl_getinfo($ch, CURLINFO_HTTP_CODE).' err '.curl_error($ch)."\n";
        curl_close($ch);
        fclose($fp);
    } elseif (ini_get('allow_url_fopen')) {
        $downloaded = @copy($url, $zipPath);
        echo 'copy '.var_export($downloaded, true)."\n";
    }
    if (! $downloaded && is_file($zipPath) && filesize($zipPath) < 1000) {
        unlink($zipPath);
    }
    echo 'zip after download '.(is_file($zipPath) ? filesize($zipPath) : 'NO')."\n";
}

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
