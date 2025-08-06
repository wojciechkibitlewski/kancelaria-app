FROM php:8.2-fpm

# Instalacja zależności (tak jak masz teraz)
RUN apt-get update && apt-get install -y \
    zip unzip curl git libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Ustaw katalog roboczy
WORKDIR /var/www

# Skopiuj projekt
COPY . /var/www

# Skopiuj composer z oficjalnego obrazu
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Uruchom Laravel
CMD php artisan serve --host=0.0.0.0 --port=8080