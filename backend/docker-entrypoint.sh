#!/bin/bash
set -e

echo "🔧 Setting up Laravel environment..."

# Always create a fresh .env with correct settings
cat > .env << EOF
APP_NAME="Champions League"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-champions_league}
DB_USERNAME=${DB_USERNAME:-cluser}
DB_PASSWORD=${DB_PASSWORD:-clpassword}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOF

echo "   ✅ .env created with MySQL connection"

# Generate app key
php artisan key:generate --force
echo "   ✅ App key generated"

# Wait for MySQL
echo "⏳ Waiting for database at ${DB_HOST:-db}:${DB_PORT:-3306}..."
for i in {1..30}; do
    if php -r "
        try {
            new PDO(
                'mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-champions_league}',
                '${DB_USERNAME:-cluser}',
                '${DB_PASSWORD:-clpassword}'
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "   ✅ Database ready!"
        break
    fi
    echo "   ⏳ Attempt $i/30 - waiting..."
    sleep 2
done

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Only seed if teams table is empty — check via php artisan tinker (native, always works)
TEAM_COUNT=$(php artisan tinker --execute="echo App\Models\Team::count();" 2>/dev/null || echo "0")
TEAM_COUNT=$(echo "$TEAM_COUNT" | tr -d '[:space:]')
if [ "$TEAM_COUNT" = "0" ] || [ -z "$TEAM_COUNT" ]; then
    echo "🌱 Running seeders (first boot — teams table empty)..."
    php artisan db:seed --force
else
    echo "✅ Skipping seed — ${TEAM_COUNT} teams already in database."
fi

# Clear and cache config
php artisan config:cache 2>/dev/null || true

# Set PHP-FPM pool config (listen on port 9001, nginx proxies to it)
cat > /usr/local/etc/php-fpm.d/www.conf << 'FPMEOF'
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9001
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
FPMEOF

echo "🚀 Starting services..."
exec "$@"
