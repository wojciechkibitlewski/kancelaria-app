FROM php:8.2-fpm

# Instalacja zależności PHP
RUN apt-get update && apt-get install -y \
    zip unzip curl git libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Ustawienie katalogu roboczego
WORKDIR /var/www

# Skopiowanie kodu aplikacji do kontenera
COPY . .

# Skopiowanie Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalacja zależności Laravelowych
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Start aplikacji Laravel
CMD php artisan serve --host=0.0.0.0 --port=8080