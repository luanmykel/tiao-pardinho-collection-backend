#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env || true
fi

export DB_CONNECTION=${DB_CONNECTION:-sqlite}
export DB_DATABASE=${DB_DATABASE:-/var/www/html/database/database.sqlite}

mkdir -p database storage/logs bootstrap/cache
if [ ! -f "$DB_DATABASE" ]; then
  touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache database || true

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if ! grep -q "^APP_KEY=" .env || [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2)" ]; then
  php artisan key:generate --force
fi

if ! grep -q "^JWT_SECRET=" .env || [ -z "$(grep '^JWT_SECRET=' .env | cut -d= -f2)" ]; then
  php artisan jwt:secret --force
fi

php artisan config:clear || true
php artisan cache:clear || true
php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

php artisan storage:link || true

touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R 775 storage bootstrap/cache database || true

exec "$@"
