FROM php:8.4-cli-alpine AS runtime

LABEL org.opencontainers.image.source="https://github.com/evilprophet/composer-parser" org.opencontainers.image.description="Compare Composer dependencies across repositories and publish consolidated reports" org.opencontainers.image.licenses="MIT"

ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/tmp/composer

RUN apk add --no-cache ca-certificates curl freetype gettext-envsubst git libjpeg-turbo libpng libxml2 libzip openssh-client-default unzip \
    && apk add --no-cache --virtual .build-dependencies $PHPIZE_DEPS curl-dev freetype-dev libjpeg-turbo-dev libpng-dev libxml2-dev libzip-dev zlib-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl dom gd simplexml xml xmlreader xmlwriter zip \
    && apk del .build-dependencies

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-autoloader --no-dev --no-interaction --no-progress --no-scripts --prefer-dist

COPY bin/ bin/
COPY config/services.yaml config/parameters.yaml.template config/
COPY src/ src/
COPY LICENSE ./LICENSE

RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction \
    && composer check-platform-reqs --no-dev \
    && addgroup -g 1000 -S composer-parser && adduser -u 1000 -S -D -G composer-parser composer-parser \
    && mkdir -p /tmp/composer var/log var/repositories var/results && chown -R composer-parser:composer-parser /tmp/composer var \
    && ln -s /app/bin/console /usr/local/bin/composer-parser

USER composer-parser

ENTRYPOINT ["composer-parser"]
CMD ["list"]
