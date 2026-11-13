FROM php:8.2.35-fpm

# PHP defaults to UTC, which made every container timestamp 7 hours behind the
# Lampung office. tzdata plus an explicit date.timezone keeps php-fpm, Carbon
# and the CLI reporting WIB even when the host timezone differs.
ENV TZ=Asia/Jakarta
RUN apt-get update && apt-get install -y \
    tzdata \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && printf 'date.timezone = %s\n' "$TZ" > /usr/local/etc/php/conf.d/timezone.ini \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

CMD ["php-fpm"]
