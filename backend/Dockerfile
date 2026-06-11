FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader
COPY . .

RUN mkdir -p var/cache var/log
RUN chmod -R 775 var

EXPOSE 8080

CMD ["frankenphp", "run"]