# ==============================================================================
# STAGE 1: Frontend Asset Compilation (Node / Vite)
# ==============================================================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN npm run build

# ==============================================================================
# STAGE 2: PHP Dependency Installation (Composer)
# ==============================================================================
FROM composer:2.8 AS vendor-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ==============================================================================
# STAGE 3: Final Production Image (PHP 8.3-FPM + Nginx + Supervisor)
# ==============================================================================
FROM php:8.3-fpm-alpine AS runner

# Install system dependencies, Nginx, and Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    tzdata

# Install PHP extensions required for Laravel & MySQL
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    xml \
    bcmath \
    opcache \
    zip \
    pcntl

# Configure OPcache for production performance
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Setup application directory
WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy built vendor dependencies from Stage 2
COPY --from=vendor-builder /app/vendor/ ./vendor/

# Copy compiled frontend assets from Stage 1
COPY --from=frontend-builder /app/public/build/ ./public/build/

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Establish directory permissions for non-root execution (www-data)
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor /var/run/nginx \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/nginx /var/lib/nginx /var/run/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose standard HTTP port
EXPOSE 80

# Environment defaults
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
