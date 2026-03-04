FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
   git \
   curl \
   zip \
   unzip \
   libpng-dev \
   libjpeg-dev \
   libfreetype6-dev \
   libonig-dev \
   libxml2-dev \
   libzip-dev \
   libsqlite3-dev \
   cron \
   && docker-php-ext-configure gd --with-freetype --with-jpeg \
   && docker-php-ext-install \
   pdo_mysql \
   pdo_sqlite \
   mbstring \
   exif \
   pcntl \
   bcmath \
   gd \
   zip \
   && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Setup storage, bootstrap/cache & SQLite
RUN mkdir -p storage bootstrap/cache database \
   && chown -R www-data:www-data storage bootstrap/cache database \
   && chmod -R 775 storage bootstrap/cache database \
   && touch database/database.sqlite \
   && chown www-data:www-data database/database.sqlite \
   && chmod 664 database/database.sqlite

# Add crontab configuration
RUN echo "* * * * * www-data cd /var/www && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/laravel-cron \
   && chmod 0644 /etc/cron.d/laravel-cron \
   && crontab /etc/cron.d/laravel-cron


COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
#COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]