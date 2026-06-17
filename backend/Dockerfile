FROM dunglas/frankenphp:php8.4

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1


RUN install-php-extensions redis pdo_pgsql intl


RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts 

COPY . .

EXPOSE 8080

CMD ["frankenphp", "run"]