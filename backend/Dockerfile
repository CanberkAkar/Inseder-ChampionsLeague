# ─── Stage 1: PHP + System Dependencies ────────────────────────────────────
FROM php:8.2-fpm AS php-base

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip nginx supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ─── Stage 2: Fresh Laravel Scaffold ────────────────────────────────────────
FROM php-base AS laravel-scaffold

WORKDIR /laravel
RUN composer create-project laravel/laravel . --prefer-dist --no-interaction --quiet

# ─── Stage 3: Final Image — Overlay Our App Files ───────────────────────────
FROM php-base AS final

WORKDIR /var/www/html

# Copy the full Laravel scaffold (includes artisan, bootstrap, config, public, etc.)
COPY --from=laravel-scaffold /laravel .

# Override with our custom application files
COPY app/         app/
COPY bootstrap/   bootstrap/
COPY database/    database/
COPY routes/      routes/
COPY tests/       tests/
COPY phpunit.xml  phpunit.xml
COPY .env.example .env.example

# Install all dependencies (including dev for tests)
RUN composer install --no-interaction --optimize-autoloader

# Storage & cache permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Supervisord config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
