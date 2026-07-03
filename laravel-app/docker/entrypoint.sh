#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

if [ "$1" = "apache2-foreground" ]; then
    php artisan migrate --force
fi

exec "$@"
