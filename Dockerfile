FROM php:8.2-fpm

# Instalacja zależności PHP
RUN apt-get update && apt-get install -y \
    zip unzip curl git libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Skopiowanie Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Ustawienie katalogu roboczego
WORKDIR /var/www

# Instalacja zależności Laravela (vendor trafi do hosta dzięki volume)
RUN composer global require laravel/installer

# Domyślny proces (php-fpm, nie artisan serve!)
CMD ["php-fpm"]
