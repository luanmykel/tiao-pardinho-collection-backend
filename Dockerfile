FROM php:8.3-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN a2enmod rewrite \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's/DocumentRoot .*/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-available/000-default.conf

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libonig-dev libicu-dev libsqlite3-dev \
 && docker-php-ext-install intl zip pdo pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction


RUN chown -R www-data:www-data storage bootstrap/cache database || true \
  && chmod -R 775 storage bootstrap/cache database || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
