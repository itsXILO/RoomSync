#!/bin/sh
set -e

# Run migrations and cache configurations when running in production
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Caching configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    
    echo "Waiting for database connection..."
    until nc -z -v -w30 db 3306 > /dev/null 2>&1 || php -r '
        $pdo = null;
        for ($i = 0; $i < 30; $i++) {
            try {
                $pdo = new PDO("mysql:host=" . env("DB_HOST", "db") . ";port=" . env("DB_PORT", 3306), env("DB_USERNAME"), env("DB_PASSWORD"));
                exit(0);
            } catch (Exception $e) {
                sleep(2);
            }
        }
        exit(1);
    '; do
        echo "Database unavailable, waiting 2 seconds..."
        sleep 2
    done

    echo "Running database migrations..."
    php artisan migrate --force || true
fi

exec "$@"
