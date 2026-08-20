# ---- Stage 1: install PHP dependencies with Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
# No committed lock file yet — resolve from composer.json.
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    && composer dump-autoload --optimize

# ---- Stage 2: application runtime ----
FROM php:8.3-apache

# PHP extensions + Apache rewrite for the front controller
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# Point Apache at the /public front-controller directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# App source + resolved vendor dir from the composer stage
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

# Writable uploads dir
RUN mkdir -p /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/public/uploads

# Entrypoint waits for the DB, applies the schema, then starts Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
