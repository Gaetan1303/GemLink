FROM dunglas/frankenphp:php8.4

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

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
RUN install-php-extensions redis pdo_pgsql intl gd

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
