# ──────────────────────────────────────────────
# Stage 1 — Composer
# ──────────────────────────────────────────────
FROM composer:latest AS vendor

WORKDIR /app

COPY ../composer.json ../composer.lock* ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --no-progress \
      --no-scripts \
      --no-ansi \
      --no-cache

# ──────────────────────────────────────────────
# Stage 2 — Worker CLI (pas de serveur HTTP)
# ──────────────────────────────────────────────
FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    postgresql-libs \
    libzip \
    icu-libs \
    && docker-php-ext-install \
       pdo_pgsql \
       intl \
       zip \
       mbstring \
       opcache

WORKDIR /app

COPY --from=vendor /app/vendor    ./vendor
COPY --from=vendor /app/composer.json ./composer.json
COPY --from=vendor /app/composer.lock ./composer.lock

COPY ../bin/        ./bin/
COPY ../config/     ./config/
COPY ../migrations/ ./migrations/
COPY ../src/        ./src/
COPY ../.env        ./.env

RUN php bin/console cache:clear --env=prod || true

# Consomme la queue Symfony Messenger en continu
CMD ["php", "bin/console", "messenger:consume", "async", "low_priority", "--time-limit=55", "--env=prod"]
