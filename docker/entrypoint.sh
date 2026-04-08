#!/bin/sh
set -e

# Change to app directory
cd /app

# Check if vendor/autoload.php exists, if not, install dependencies
if [ ! -f "vendor/autoload.php" ]; then
    echo "vendor/autoload.php not found. Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Check if node_modules exists, if not, install npm dependencies
if [ ! -d "node_modules" ]; then
    echo "node_modules not found. Installing npm dependencies..."
    npm install
fi

# Set proper permissions for storage and bootstrap/cache
if [ -d "storage" ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
fi

# Generate APP_KEY if not set
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    echo "APP_KEY is empty. Generating a new key..."
    php artisan key:generate --force
fi

# Build frontend assets if manifest doesn't exist
# if [ ! -f "public/build/manifest.json" ]; then
#     echo "Vite manifest not found. Building frontend assets..."
#     npm run build
# fi

if [ "$APP_ENV" = "local" ]; then
    echo "Running in Development mode..."
    npm run dev -- --host 0.0.0.0 & # Jalankan di background
else
    echo "Running in Production mode..."
    npm run build
fi

# Execute the main command (octane:frankenphp with arguments)
exec php artisan octane:frankenphp "$@"
