FROM php:8.4-fpm-bookworm

WORKDIR /var/www

RUN apt-get update && apt-get install -y --no-install-recommends \
    default-mysql-client \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libsqlite3-dev \
    libzip-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite mbstring zip exif pcntl gd opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www \
    && chown www:www /var/www

COPY --chown=www:www . /var/www

USER www

RUN composer install --prefer-dist --no-interaction --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]
