# syntax=docker/dockerfile:1

# ---- Frontend assets ---------------------------------------------------------
FROM docker.io/library/node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
RUN npm run build

# ---- PHP dependencies (production only) --------------------------------------
FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --classmap-authoritative

# ---- Runtime -----------------------------------------------------------------
FROM docker.io/dunglas/frankenphp:1-php8.5-bookworm

RUN install-php-extensions pdo_sqlite intl bcmath opcache pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends supervisor sqlite3 curl \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-finegrain.ini"
COPY docker/supervisord.conf /etc/supervisor/conf.d/finegrain.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/finegrain-entrypoint

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

RUN php artisan package:discover --ansi \
    && mkdir -p /data storage/framework/{cache/data,sessions,views} storage/logs \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data

# The FrankenPHP image points Caddy's state at /data; keep that out of our volume.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    XDG_DATA_HOME=/tmp/caddy/data \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    SERVER_NAME=:8080

EXPOSE 8080
VOLUME ["/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["finegrain-entrypoint"]
