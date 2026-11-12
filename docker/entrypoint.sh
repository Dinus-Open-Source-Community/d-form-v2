#!/bin/sh
set -e

# Change to app directory
cd /app

echo "Checking dependencies..."

# Fix dubious ownership for git
git config --global --add safe.directory /app || true

# Ensure necessary directories exist before anything else
echo "Preparing directories..."
mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         bootstrap/cache/filament

# Check if composer dependencies are installed
if [ ! -f "vendor/autoload.php" ]; then
    echo "Composer dependencies not found. Installing..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure node_modules match the container platform (Alpine/musl native bindings)
ensure_node_modules() {
    if [ -d "node_modules" ] && [ -x "node_modules/.bin/vite" ] \
        && node -e "require('lightningcss')" >/dev/null 2>&1; then
        return 0
    fi

    echo "Node modules missing or native bindings incompatible. Running npm install..."
    npm install
}

ensure_node_modules

# Ensure permissions - www-data is the user for frankenphp/caddy
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Wait for database
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database ($DB_HOST)..."
    until nc -z -v -w30 "$DB_HOST" 3306; do
        echo "Database is unavailable - sleeping"
        sleep 2
    done
    echo "Database is up!"
fi

# Clear cache that might be stale or have wrong paths
echo "Clearing stale cache..."
rm -f bootstrap/cache/*.php

# Public disk: symlink public/storage -> storage/app/public
echo "Ensuring storage link..."
php artisan storage:link --force 2>/dev/null || true

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run migrations in local
if [ "$APP_ENV" = "local" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

if [ "$APP_ENV" = "local" ]; then
    echo "Starting Vite..."
    # Run vite with host flag to allow access from outside
    npm run dev -- --host 0.0.0.0 &
else
    echo "Building assets..."
    npm run build
fi

# Inertia SSR lokal: build sekali bila belum ada, lalu jalankan dengan loop restart
# supaya error render langsung terlihat di log container. Tanpa proses ini Laravel
# otomatis fallback ke render client-side.
if [ "$APP_ENV" = "local" ]; then
    if [ ! -f /app/bootstrap/ssr/ssr.js ]; then
        echo "Building SSR bundle (sekali)..."
        npm run build:ssr
    fi

    echo "Starting Inertia SSR..."
    (
        while true; do
            node /app/bootstrap/ssr/ssr.js || true
            echo "SSR process exited; restarting in 2s..." >&2
            sleep 2
        done
    ) &
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
# Execute as www-data if possible, or frankenphp will handle it
exec php artisan octane:frankenphp "$@" --host=0.0.0.0 --port=8000
