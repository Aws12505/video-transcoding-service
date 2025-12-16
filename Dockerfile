FROM php:8.4-apache

# --- OS deps + ffmpeg ---
RUN apt-get update && apt-get install -y \
    git zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libwebp-dev \
    build-essential \
    ffmpeg \
 && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip pcntl sockets

# --- Apache: Laravel needs /public as webroot + .htaccess ---
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && a2enmod rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# --- PHP limits for large files ---
RUN echo "upload_max_filesize = 10G" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size = 10G" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "max_execution_time = 7200" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# --- App code ---
COPY . /var/www/html

# IMPORTANT: in real production, you should COPY your real .env (not .env.example)
# This keeps your previous behavior, but you should replace this later:
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Install deps + Laravel prep
RUN composer install --no-dev --optimize-autoloader \
 && php artisan key:generate --force \
 && php artisan config:cache \
 && php artisan route:cache || true

# Permissions
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache
