#!/bin/sh
set -e

cd /var/www

php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Crear el enlace simbólico public/storage -> storage/app/public
# (necesario para que las imágenes subidas sean accesibles vía URL).
# Solo lo crea si no existe ya, para no fallar si el volumen persistente
# ya lo trae de un arranque anterior.
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link
fi

# Corregir permisos por si los comandos anteriores (corridos como root)
# crearon archivos que www-data (el usuario de php-fpm) necesita poder escribir.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php-fpm -D
nginx -g "daemon off;"