FROM dunglas/frankenphp:php8.4

WORKDIR /app


ENV COMPOSER_ALLOW_SUPERUSER=1


RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql


COPY composer.json composer.lock ./

# 2. Installer les dépendances
RUN composer install --no-dev --prefer-dist --optimize-autoloader


COPY . .


RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


RUN mkdir -p var/cache var/log && chmod -R 775 var

EXPOSE 8080

CMD ["frankenphp", "run"]