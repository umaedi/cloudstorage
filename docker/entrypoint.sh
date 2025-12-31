#!/bin/sh
set -e

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Create storage link if it doesn't exist
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link
fi

# Execute the main command
exec "$@"
