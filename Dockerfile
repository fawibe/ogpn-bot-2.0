FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-libs libcurl oniguruma \
 && apk add --no-cache --virtual .build-deps curl-dev icu-dev oniguruma-dev \
 && docker-php-ext-install -j"$(nproc)" curl intl mbstring \
 && apk del .build-deps \
 && printf 'memory_limit=384M\n' > /usr/local/etc/php/conf.d/ogpn-worker.ini

WORKDIR /app
COPY . /app

RUN chown -R www-data:www-data /app/storage

USER www-data

CMD ["php", "worker/run.php"]
