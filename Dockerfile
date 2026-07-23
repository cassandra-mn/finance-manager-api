FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Runtime libraries stay installed; build-only headers/tools live in the
# .build-deps virtual package and are removed at the end of this layer to
# keep the final image slim.
RUN apk add --no-cache \
        bash \
        curl \
        nginx \
        supervisor \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        icu-libs \
        oniguruma \
        postgresql-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_pgsql \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache \
        intl \
        mbstring \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# route:cache/view:cache don't depend on runtime secrets, so they're safe to
# bake into the image. config:cache and migrate run at container start
# instead (see docker/entrypoint.sh) — they need the real DB/APP_KEY env vars
# Render only provides once the container is actually running.
RUN php artisan route:cache \
    && php artisan view:cache

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php-fpm/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/php-fpm/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
