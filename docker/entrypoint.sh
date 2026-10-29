#!/bin/sh
set -e

# Run migrations and cache configurations when running in production
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Caching configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    
    echo "Running database migrations..."
    php artisan migrate --force || true
fi

exec "$@"
