FROM php:8.2-fpm

RUN apt-get update \
  && apt-get install -y git unzip libzip-dev libpq-dev libonig-dev \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && docker-php-ext-install pdo_pgsql zip mbstring exif pcntl bcmath \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["entrypoint.sh"]
