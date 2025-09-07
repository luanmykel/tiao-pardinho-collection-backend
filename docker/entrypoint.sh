#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html
umask 002

if [ ! -f .env ]; then
  cp .env.example .env || true
fi

export DB_CONNECTION=${DB_CONNECTION:-sqlite}
export DB_DATABASE=${DB_DATABASE:-/var/www/html/database/database.sqlite}

mkdir -p storage/logs bootstrap/cache
if [ "${DB_CONNECTION}" = "sqlite" ]; then
  mkdir -p database
  [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache database || true

if [ ! -d vendor ]; then
    git config --global --add safe.directory /var/www/html || true
    chown -R www-data:www-data /var/www/html
    if [ "$(id -u)" = "0" ]; then
        runuser -u www-data -- composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    else
        composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    fi
fi

if ! grep -q "^APP_KEY=" .env || [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2)" ]; then
  php artisan key:generate --force
fi

if ! grep -q "^JWT_SECRET=" .env || [ -z "$(grep '^JWT_SECRET=' .env | cut -d= -f2)" ]; then
  php artisan jwt:secret --force
fi

php artisan config:clear || true
php artisan cache:clear || true
php artisan migrate --force --no-interaction
touch storage/logs/seed.log || true
( php artisan db:seed --force --no-interaction >> storage/logs/seed.log 2>&1 & ) || true
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

php artisan storage:link || true

touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache database || true

exec "$@"
