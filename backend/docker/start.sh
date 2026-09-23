#!/bin/bash
set -e

# ------------------------------------------------------------------
# AulaSync — Docker startup script
# Replaces `php artisan serve` (single-process) with nginx + php-fpm
# ------------------------------------------------------------------

# Render / Railway / Fly.io export PORT; default to 8080
export NGINX_PORT="${PORT:-8080}"

echo "[start.sh] Booting AulaSync on port $NGINX_PORT (php-fpm + nginx)"

# 1. Substitute ${NGINX_PORT} in nginx template → real config
envsubst '${NGINX_PORT}' < /etc/nginx/conf.d/aulasync.conf.template \
    > /etc/nginx/conf.d/default.conf

# 2. Laravel boot tasks (idempotent)
php artisan config:cache  --no-interaction
php artisan route:cache   --no-interaction
php artisan view:cache    --no-interaction
php artisan migrate       --force --no-interaction

# 3. Ensure storage symlink exists (public disk)
php artisan storage:link --force 2>/dev/null || true

# 4. Fix permissions (important in some cloud envs)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 5. Start php-fpm as daemon, nginx in foreground (keeps container alive)
php-fpm -D
echo "[start.sh] php-fpm started"

exec nginx -g 'daemon off;'
