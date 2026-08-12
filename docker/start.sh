#!/bin/sh
set -e

cd /var/www

php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Corregir permisos por si los comandos anteriores (corridos como root)
# crearon archivos que www-data (el usuario de php-fpm) necesita poder escribir.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php-fpm -D
nginx -g "daemon off;"