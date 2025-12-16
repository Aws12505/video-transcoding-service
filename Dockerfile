FROM php:8.4-apache

# OS deps + ffmpeg
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libwebp-dev \
    build-essential \
    ffmpeg \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip pcntl sockets

# Apache: Laravel public/ + .htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && a2enmod rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# PHP limits
RUN echo "upload_max_filesize = 10G" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 10G" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "max_execution_time = 7200" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# App code (will be overridden by bind mount in dev)
COPY . /var/www/html

# Dev convenience
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Install deps (vendor lives in your project folder)
RUN composer install --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html
