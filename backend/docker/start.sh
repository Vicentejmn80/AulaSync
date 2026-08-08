#!/usr/bin/env sh
set -e

cd /var/www/html

export PORT="${PORT:-8080}"

php artisan config:cache
php artisan route:cache
php artisan view:cache

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

php-fpm -D
exec nginx -g 'daemon off;'
