FROM php:8.4-cli-alpine
RUN docker-php-ext-install pdo_mysql && apk add --no-cache curl-dev icu-dev && docker-php-ext-install intl && docker-php-ext-enable intl
WORKDIR /app
COPY . /app
CMD ["php","worker/run.php"]
