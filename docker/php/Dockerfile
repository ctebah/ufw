FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install mysqli \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && printf 'pcov.enabled=1\n' > /usr/local/etc/php/conf.d/zz-pcov.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'if [ ! -f vendor/autoload.php ]; then composer install --no-interaction --prefer-dist; fi' \
    'exec "$@"' \
    > /usr/local/bin/ufw-test-entrypoint \
    && chmod +x /usr/local/bin/ufw-test-entrypoint

ENTRYPOINT ["ufw-test-entrypoint"]
CMD ["vendor/bin/phpunit"]
