<?php

/**
 * Prueba temporal de conexión MySQL (lee DB_* del .env de Laravel).
 *
 * URL: https://webapi.chefmargaro.com/db-connection-test.php?token=chefmargaro-db-test-2026
 *
 * IMPORTANTE: elimina este archivo en cuanto termines la prueba.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$expectedToken = 'chefmargaro-db-test-2026';

if (($_GET['token'] ?? '') !== $expectedToken) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><p>Not found</p></body></html>';
    exit;
}

function loadEnvFile(string $path): array
{
    if (! is_readable($path)) {
        throw new RuntimeException('.env no encontrado o no legible: '.$path);
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $vars[$name] = $value;
    }

    return $vars;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$envPath = dirname(__DIR__).'/.env';
$startedAt = microtime(true);

echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
echo '<title>Prueba de conexión MySQL</title>';
echo '<style>
body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 1rem;line-height:1.5}
.ok{color:#0a7a2f;background:#e8f8ee;border:1px solid #b8e6c8;padding:1rem;border-radius:8px}
.err{color:#9b1c1c;background:#fdecec;border:1px solid #f5c2c2;padding:1rem;border-radius:8px}
.warn{color:#7a5c00;background:#fff8e6;border:1px solid #f0e0a0;padding:1rem;border-radius:8px;margin-top:1rem}
code{background:#f4f4f4;padding:.15rem .35rem;border-radius:4px}
table{border-collapse:collapse;width:100%;margin-top:1rem}
td,th{border:1px solid #ddd;padding:.5rem .75rem;text-align:left}
th{background:#f8f8f8;width:38%}
</style></head><body>';
echo '<h1>Prueba de conexión MySQL</h1>';

try {
    $env = loadEnvFile($envPath);

    $config = [
        'DB_CONNECTION' => $env['DB_CONNECTION'] ?? '(no definido)',
        'DB_HOST' => $env['DB_HOST'] ?? '127.0.0.1',
        'DB_PORT' => $env['DB_PORT'] ?? '3306',
        'DB_DATABASE' => $env['DB_DATABASE'] ?? '',
        'DB_USERNAME' => $env['DB_USERNAME'] ?? '',
        'DB_PASSWORD' => $env['DB_PASSWORD'] ?? '',
    ];

    echo '<table><tbody>';
    foreach ($config as $key => $value) {
        $display = $key === 'DB_PASSWORD'
            ? (strlen((string) $value) > 0 ? str_repeat('•', min(12, strlen((string) $value))) : '(vacía)')
            : h((string) $value);
        echo '<tr><th>'.h($key).'</th><td>'.$display.'</td></tr>';
    }
    echo '</tbody></table>';

    if (($config['DB_CONNECTION'] ?? '') !== 'mysql') {
        throw new RuntimeException('DB_CONNECTION no es mysql.');
    }

    if ($config['DB_DATABASE'] === '' || $config['DB_USERNAME'] === '') {
        throw new RuntimeException('Faltan DB_DATABASE o DB_USERNAME en .env');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['DB_HOST'],
        $config['DB_PORT'],
        $config['DB_DATABASE']
    );

    $pdo = new PDO(
        $dsn,
        $config['DB_USERNAME'],
        $config['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $version = $pdo->query('SELECT VERSION() AS v')->fetch()['v'] ?? 'desconocida';
    $ordersCount = $pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'] ?? '?';
    $elapsedMs = round((microtime(true) - $startedAt) * 1000);

    echo '<div class="ok" style="margin-top:1rem">';
    echo '<strong>Conexión correcta.</strong><br>';
    echo 'MySQL: <code>'.h((string) $version).'</code><br>';
    echo 'Base de datos: <code>'.h((string) $config['DB_DATABASE']).'</code><br>';
    echo 'Registros en tabla <code>orders</code>: <code>'.h((string) $ordersCount).'</code><br>';
    echo 'Tiempo: <code>'.$elapsedMs.' ms</code>';
    echo '</div>';
} catch (Throwable $e) {
    echo '<div class="err" style="margin-top:1rem">';
    echo '<strong>Conexión fallida.</strong><br>';
    echo 'Error: <code>'.h($e->getMessage()).'</code>';
    echo '</div>';

    if ($e instanceof PDOException) {
        echo '<p>Revisa en el panel del hosting: usuario, contraseña, nombre de BD y que el usuario esté asignado a esa base.</p>';
    }
}

echo '<div class="warn">';
echo '<strong>Seguridad:</strong> borra el archivo <code>public/db-connection-test.php</code> del servidor cuando termines.';
echo '</div>';
echo '</body></html>';
