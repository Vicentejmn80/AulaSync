FROM php:8.2-fpm

# Instalar dependencias del sistema, nginx, gettext (envsubst) y Node.js
RUN apt-get update && apt-get install -y \
    nginx \
    gettext-base \
    git \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensiones de PHP (+ opcache para producción)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache gd

# OPcache tunning para producción
RUN echo "opcache.enable=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=8\nopcache.max_accelerated_files=10000\nopcache.revalidate_freq=0\nopcache.validate_timestamps=0\nopcache.fast_shutdown=1" \
    > /usr/local/etc/php/conf.d/opcache.ini

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de PHP y Node.js, y compilar assets de Vite
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm install \
    && npm run build

# Configurar nginx — copiamos la plantilla (con ${NGINX_PORT}) al directorio de templates
RUN mkdir -p /etc/nginx/conf.d
COPY docker/nginx.conf /etc/nginx/conf.d/aulasync.conf.template

# Eliminar la config default de nginx que ocupa el puerto 80
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

# Script de arranque
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Permisos de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public

EXPOSE 8080

# ⚠️  ANTES: php artisan serve (single-process, bloquea todos los requests)
# AHORA: nginx (reverse proxy) + php-fpm (pool de workers paralelos)
CMD ["/start.sh"]
