#!/bin/sh
set -e

cd /app

echo "=== D-Form Production Entrypoint ==="

# Ensure necessary directories exist
echo "Preparing directories..."
mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         bootstrap/cache/filament

# Fix permissions
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Wait for database
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database ($DB_HOST:${DB_PORT:-3306})..."
    until nc -z -v -w30 "$DB_HOST" "${DB_PORT:-3306}"; do
        echo "Database is unavailable - sleeping"
        sleep 2
    done
    echo "Database is up!"
fi

# Wait for Redis
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis ($REDIS_HOST:${REDIS_PORT:-6379})..."
    until nc -z -v -w30 "$REDIS_HOST" "${REDIS_PORT:-6379}"; do
        echo "Redis is unavailable - sleeping"
        sleep 2
    done
    echo "Redis is up!"
fi

# Storage symlink
echo "Ensuring storage link..."
php artisan storage:link --force 2>/dev/null || true

# ---------------------------------------------------------------
# Write a custom worker file that catches boot exceptions.
# The default Octane worker has no try/catch around ->boot(),
# so PHP exceptions during app bootstrap are swallowed silently
# by FrankenPHP, causing the cryptic "has not reached
# frankenphp_handle_request()" error with no trace in docker logs.
# ---------------------------------------------------------------
echo "Writing diagnostic FrankenPHP worker..."
cat > public/frankenphp-worker.php << 'WORKER_PHP'
<?php

use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\FrankenPhp\FrankenPhpClient;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Stream;
use Laravel\Octane\Worker;
use Symfony\Component\HttpFoundation\Response;

// ── File-based trace log — writes to the PERSISTENT Docker volume so the file
// survives container restarts (storage/app is mounted as a volume).
$_traceLog = '/app/storage/app/worker-trace.log';
$_trace = function (string $msg) use ($_traceLog): void {
    $line = '[' . date('H:i:s') . '][pid=' . getmypid() . '] ' . $msg . PHP_EOL;
    // NOTE: Do NOT use fwrite(STDERR, ...) here!
    // In FrankenPHP's embedded PHP ZTS (threaded) mode, STDERR is not a valid
    // stream handle for PHP threads and causes a segfault, killing all workers.
    file_put_contents($_traceLog, $line, FILE_APPEND | LOCK_EX);
};

$_trace('=== Worker script started ===');
$_trace('SAPI: ' . PHP_SAPI);
$_trace('FRANKENPHP_WORKER(server): ' . ($_SERVER['FRANKENPHP_WORKER'] ?? 'NOT SET'));
$_trace('FRANKENPHP_WORKER(env):    ' . ($_ENV['FRANKENPHP_WORKER'] ?? 'NOT SET'));
$_trace('frankenphp_handle_request exists: ' . (function_exists('frankenphp_handle_request') ? 'YES' : 'NO'));

$_SERVER['APP_BASE_PATH'] = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? __DIR__.'/..';
$_SERVER['APP_PUBLIC_PATH'] = $_ENV['APP_PUBLIC_PATH'] ?? $_SERVER['APP_PUBLIC_PATH'] ?? __DIR__;

$_trace('APP_BASE_PATH: ' . $_SERVER['APP_BASE_PATH']);

if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    $_trace('CHECK FAILED - not in FrankenPHP worker mode or function missing. Exiting.');
    echo 'FrankenPHP must be in worker mode to use this script.';
    exit(1);
}

$_trace('Check passed - in FrankenPHP worker mode.');

ignore_user_abort(true);

$_trace('Loading bootstrap.php...');
$basePath = require __DIR__.'/../vendor/laravel/octane/bin/bootstrap.php';
$_trace('Bootstrap loaded. basePath=' . $basePath);

try {
    $_trace('Creating FrankenPhpClient...');
    $frankenPhpClient = new FrankenPhpClient();

    $_trace('Booting Octane Worker...');
    $worker = tap(new Worker(
        new ApplicationFactory($basePath), $frankenPhpClient
    ))->boot();
    $_trace('Worker booted successfully! Entering request loop.');
} catch (\Throwable $e) {
    $msg = sprintf(
        '[WORKER-BOOT-ERROR] %s: %s  File: %s:%d  Trace: %s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    $_trace($msg);
    fwrite(STDERR, $msg . PHP_EOL);
    usleep(200000);
    exit(1);
}
// ─────────────────────────────────────────────────────────────────────────

$requestCount = 0;
$debugMode = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';
$maxRequests = $_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS'] ?? 1000;
$requestMaxExecutionTime = $_ENV['REQUEST_MAX_EXECUTION_TIME'] ?? $_SERVER['REQUEST_MAX_EXECUTION_TIME'] ?? null;

if (PHP_OS_FAMILY === 'Linux' && ! is_null($requestMaxExecutionTime)) {
    set_time_limit((int) $requestMaxExecutionTime);
}

try {
    $handleRequest = static function () use ($worker, $frankenPhpClient, $debugMode) {
        try {
            [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

            $worker->handle($request, $context);
        } catch (Throwable $e) {
            if ($worker) {
                report($e);
            }

            $response = new Response(
                $debugMode === 'true' ? (string) $e : 'Internal Server Error',
                500,
                [
                    'Status' => '500 Internal Server Error',
                    'Content-Type' => 'text/plain',
                ],
            );

            $response->send();

            Stream::shutdown($e);
        }
    };

    while ($requestCount < $maxRequests && frankenphp_handle_request($handleRequest)) {
        $requestCount++;
    }
} finally {
    $worker?->terminate();

    gc_collect_cycles();
}
WORKER_PHP

echo "Worker file written."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Optimize Laravel for production
echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Inertia SSR (Node) berjalan di dalam container yang sama; Laravel memanggil
# http://127.0.0.1:13714. Loop ini me-restart proses bila crash; bila SSR mati
# total, HttpGateway mengembalikan null dan Inertia otomatis fallback ke CSR.
if [ -f /app/bootstrap/ssr/ssr.js ]; then
    echo "Starting Inertia SSR..."
    (
        while true; do
            node /app/bootstrap/ssr/ssr.js || true
            echo "SSR process exited; restarting in 2s..." >&2
            sleep 2
        done
    ) &
else
    echo "SSR bundle tidak ada; HTML dirender client-side." >&2
fi

# Worker queue OTP: email registrasi dikirim lewat queue Redis. Matikan dengan
# QUEUE_WORKER=0 bila worker dijalankan terpisah; loop restart menjaga worker hidup.
if [ "${QUEUE_WORKER:-1}" = "1" ]; then
    echo "Starting queue worker..."
    (
        while true; do
            php artisan queue:work redis --sleep=1 --tries=3 --timeout=90 || true
            echo "Queue worker exited; restarting in 2s..." >&2
            sleep 2
        done
    ) &
fi

echo "Starting Octane with FrankenPHP..."
exec php artisan octane:frankenphp "$@" --host=0.0.0.0 --port=8000
