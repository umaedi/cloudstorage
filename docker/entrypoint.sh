#!/bin/sh
set -e

echo "Starting scheduler..."
su -s /bin/sh -c "nohup php artisan schedule:work > storage/logs/scheduler.log 2>&1 &" www-data

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Create storage link if it doesn't exist
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link
fi

echo "Starting php-fpm..."
exec "$@"