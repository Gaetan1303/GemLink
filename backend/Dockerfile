FROM dunglas/frankenphp:php8.4

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    ffmpeg \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# US 4.2 - CA-3 : gd ajouté pour endroid/qr-code (PngWriter en dépend pour
# rasteriser le PNG). install-php-extensions résout lui-même les libs
# système nécessaires (libpng, libjpeg, libwebp...), pas besoin de les
# lister manuellement via apt-get comme pour une install PHP classique.
RUN install-php-extensions redis pdo_pgsql intl gd exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --prefer-dist \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --no-scripts 
COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r '$c=@file_get_contents("http://127.0.0.1:8080/health"); exit($c===false?1:0);'

CMD ["frankenphp", "run"]
