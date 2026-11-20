FROM php:8.4-cli-alpine
RUN --mount=type=secret,id=build_ca \
    cp /etc/ssl/certs/ca-certificates.crt /tmp/build-ca.pem && if [ -f /run/secrets/build_ca ]; then cat /run/secrets/build_ca >> /tmp/build-ca.pem; fi && \
    SSL_CERT_FILE=/tmp/build-ca.pem apk add --no-cache libpq icu-libs libzip && SSL_CERT_FILE=/tmp/build-ca.pem apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev && docker-php-ext-install pdo_pgsql intl zip pcntl && apk del .build-deps && rm /tmp/build-ca.pem
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=build_ca \
    cp /etc/ssl/certs/ca-certificates.crt /tmp/build-ca.pem && if [ -f /run/secrets/build_ca ]; then cat /run/secrets/build_ca >> /tmp/build-ca.pem; fi && \
    COMPOSER_CAFILE=/tmp/build-ca.pem composer install --no-interaction --prefer-dist --no-scripts && rm /tmp/build-ca.pem
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize && touch .env && chown www-data:www-data .env && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php.ini /usr/local/etc/php/conf.d/afra.ini
USER www-data
EXPOSE 8000
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000","--no-reload"]
