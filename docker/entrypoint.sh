#!/bin/sh
set -eu

DATA_DIR=/data
mkdir -p "$DATA_DIR"

# Generate and persist an app key on first boot unless one is supplied.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$DATA_DIR/app.key" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATA_DIR/app.key"
        chmod 600 "$DATA_DIR/app.key"
    fi
    APP_KEY="$(cat "$DATA_DIR/app.key")"
    export APP_KEY
fi

touch "$DATA_DIR/database.sqlite"
chown -R www-data:www-data "$DATA_DIR"

su -s /bin/sh www-data -c "php /app/artisan migrate --force --no-interaction"
su -s /bin/sh www-data -c "php /app/artisan optimize"

if [ -n "${DASHBOARD_USERNAME:-}" ] && [ -n "${DASHBOARD_PASSWORD:-}" ]; then
    echo "finegrain: login enabled for user '${DASHBOARD_USERNAME}'."
else
    echo "finegrain: login disabled (set DASHBOARD_USERNAME and DASHBOARD_PASSWORD to enable)."
fi

if [ -z "${OCTOPUS_GRAPHQL_API_KEY:-}" ]; then
    echo "finegrain: live Home Mini data disabled (OCTOPUS_GRAPHQL_API_KEY not set)."
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/finegrain.conf
