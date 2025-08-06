FROM php:8.2-cli

# Podstawowe narzędzia
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmcrypt-dev \
    && docker-php-ext-install pdo pdo_pgsql zip mbstring xml gd

# Instalacja composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalacja Node.js (jeśli używasz Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

WORKDIR /workspace