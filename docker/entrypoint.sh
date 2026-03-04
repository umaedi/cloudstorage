#!/bin/sh
set -e

echo "Starting scheduler..."
php artisan schedule:work &

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Create storage link if it doesn't exist
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link
fi

echo "Starting php-fpm..."
exec "$@"