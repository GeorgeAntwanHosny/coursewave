# syntax=docker/dockerfile:1.7

###############################################################################
# base: PHP 8.4 FPM + the extensions CourseWave needs + nginx + supervisor
###############################################################################
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache nginx supervisor curl su-exec tzdata \
 && install-php-extensions pdo_pgsql bcmath intl zip gd exif pcntl sockets opcache redis \
 && mkdir -p /run/nginx

###############################################################################
# vendor: composer install on the SAME php image, so platform checks are real
###############################################################################
FROM base AS vendor

RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

###############################################################################
# final
###############################################################################
FROM base AS final

WORKDIR /var/www/html
ENV CONTAINER_ROLE=web \
    QUEUE_WORKERS=2

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-coursewave.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-coursewave.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/ /etc/supervisor/roles/
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Windows checkouts can turn these into CRLF, which breaks them in Alpine; normalize defensively.
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh /etc/supervisor/roles/*.conf /etc/nginx/http.d/default.conf \
 && chmod +x /usr/local/bin/docker-entrypoint.sh \
 && mkdir -p storage/app/public storage/app/private/certificates storage/fonts \
             storage/framework/cache/data storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000 8080

ENTRYPOINT ["docker-entrypoint.sh"]
