FROM php:8.2-fpm

# Instalar dependencias del sistema y Node.js (con npm)
RUN apt-get update && apt-get install -y \
    nginx \
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

# Configurar e instalar extensiones de PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de PHP y Node.js, y compilar assets de Vite
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm install \
    && npm run build

# Permisos de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public

EXPOSE 80

CMD php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
