<?php

/**
 * Diagnóstico del endpoint GET /api/v1/orders (misma lógica que OrderController::index).
 *
 * URL: https://webapi.chefmargaro.com/api-orders-debug.php?token=chefmargaro-db-test-2026
 *
 * ELIMINA este archivo cuando termines.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$expectedToken = 'chefmargaro-db-test-2026';

if (($_GET['token'] ?? '') !== $expectedToken) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function box(string $class, string $title, string $body): void
{
    echo '<div class="'.$class.'" style="margin:1rem 0;padding:1rem;border-radius:8px">';
    echo '<strong>'.h($title).'</strong><br><pre style="white-space:pre-wrap;word-break:break-word;margin:.5rem 0 0">'.h($body).'</pre>';
    echo '</div>';
}

echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Debug orders API</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:1.5rem auto;padding:0 1rem}
.ok{background:#e8f8ee;border:1px solid #b8e6c8;color:#0a5d24}
.err{background:#fdecec;border:1px solid #f5c2c2;color:#8b0000}
.info{background:#eef4ff;border:1px solid #c5d8ff;color:#123}
</style></head><body><h1>Debug: GET /api/v1/orders</h1>';

$steps = [];

try {
    require __DIR__.'/../vendor/autoload.php';
    $steps[] = 'Autoload OK';

    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $steps[] = 'Laravel bootstrap OK';
    $steps[] = 'APP_ENV='.config('app.env').' | APP_DEBUG='.(config('app.debug') ? 'true' : 'false');
    $steps[] = 'DB='.config('database.connections.mysql.database').' @ '.config('database.connections.mysql.host');

    Illuminate\Support\Facades\DB::connection()->getPdo();
    $steps[] = 'DB::connection()->getPdo() OK';

    $query = App\Models\Order::with([
        'customer',
        'orderItems.product',
        'orderItems.combo',
        'orderItems.extra',
        'payments',
    ]);

    $orders = $query->paginate(1);
    $steps[] = 'Order::with(...)->paginate(1) OK — total: '.$orders->total();

    $payload = App\Http\Resources\Api\V1\OrderResource::collection($orders)
        ->response()
        ->getData(true);

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $steps[] = 'OrderResource::collection JSON OK';

    box('ok', 'Todo correcto (misma lógica que el endpoint)', implode("\n", $steps));
    echo '<h2>Respuesta JSON (preview)</h2>';
    echo '<pre style="background:#f6f6f6;padding:1rem;overflow:auto;max-height:480px">'.h($json).'</pre>';
    echo '<p>Si esto funciona pero <code>/api/v1/orders</code> sigue en 500, revisa <code>storage/logs/laravel.log</code>, permisos de <code>storage/</code> y que el dominio apunte a esta carpeta <code>public/</code>.</p>';
} catch (Throwable $e) {
    box('err', 'Error capturado (esta es la causa del HTTP 500)', implode("\n", $steps));
    box('err', get_class($e), $e->getMessage());
    box('info', 'Archivo', $e->getFile().':'.$e->getLine());
    box('info', 'Stack trace', $e->getTraceAsString());

    $prev = $e->getPrevious();
    if ($prev instanceof Throwable) {
        box('err', 'Causa anterior: '.get_class($prev), $prev->getMessage());
    }
}

echo '<p style="color:#666"><strong>Seguridad:</strong> borra <code>public/api-orders-debug.php</code> y <code>public/db-connection-test.php</code> del servidor.</p>';
echo '</body></html>';
