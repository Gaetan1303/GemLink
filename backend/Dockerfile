FROM dunglas/frankenphp:php8.3

WORKDIR /app

# Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Extension PostgreSQL
RUN docker-php-ext-install pdo_pgsql

# Copier composer d'abord pour optimiser le cache Docker
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# Copier le reste du projet
COPY . .

# Répertoire Symfony
RUN mkdir -p var/cache var/log
RUN chmod -R 775 var

# Port Railway
EXPOSE 8080

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]