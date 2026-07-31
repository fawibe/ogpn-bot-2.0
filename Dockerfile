FROM php:8.4-cli-alpine
RUN apk add --no-cache curl-dev icu-dev oniguruma-dev \
 && docker-php-ext-install pdo_mysql curl intl mbstring \
 && apk del oniguruma-dev
WORKDIR /app
COPY . /app
CMD ["php","worker/run.php"]
