FROM php:8.4-apache

# --- OS deps + ffmpeg ---
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libwebp-dev \
    build-essential \
    ffmpeg \
 && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip pcntl sockets

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

# --- Copy only composer files first for better cache ---
COPY composer.json composer.lock* /var/www/html/

# Install vendor deps into the image (fast startup).
# Your code is bind-mounted in dev, but vendor stays available in the image.
RUN composer install --no-interaction --prefer-dist

# --- Copy the rest (still useful for non-bind-mount runs) ---
COPY . /var/www/html

# Create .env if missing (dev convenience)
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Permissions
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache
