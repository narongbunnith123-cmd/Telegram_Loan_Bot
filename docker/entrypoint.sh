#!/bin/sh
set -e

echo "========================================="
echo "  Starting Laravel Application Deploy"
echo "========================================="

cd /var/www/html

# Ensure storage directories exist
mkdir -p storage/logs
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache/data
mkdir -p bootstrap/cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Create storage link if it doesn't exist
php artisan storage:link --force 2>/dev/null || true

# Discover packages (skipped during build)
echo "→ Discovering packages..."
php artisan package:discover --ansi || true

# Cache configuration for performance
echo "→ Caching configuration..."
php artisan config:cache
echo "→ Caching routes..."
php artisan route:cache
echo "→ Caching views..."
php artisan view:cache

# Run database migrations (non-fatal - app starts even if DB isn't ready)
echo "→ Running migrations..."
php artisan migrate --force || echo "⚠ Migration failed - will retry on next deploy"

echo "========================================="
echo "  Deploy complete. Starting services..."
echo "========================================="

# Start supervisor (manages php-fpm, nginx, queue worker, scheduler)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
