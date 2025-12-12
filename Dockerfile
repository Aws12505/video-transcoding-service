FROM php:8.4-apache

# Install dependencies + FFmpeg
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libwebp-dev \
    build-essential \
    ffmpeg \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip pcntl sockets

# PHP limits for large files
RUN echo "upload_max_filesize = 10G" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 7200" >> /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html
COPY .env.example /var/www/html/.env

RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

WORKDIR /var/www/html

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader
RUN php artisan key:generate

# Storage directories
RUN mkdir -p storage/app/temp storage/app/transcoded storage/logs bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache
