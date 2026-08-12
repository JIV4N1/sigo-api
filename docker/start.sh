#!/bin/sh
set -e

cd /var/www

php artisan config:cache
php artisan route:cache
php artisan migrate --force

php-fpm -D
nginx -g "daemon off;"
