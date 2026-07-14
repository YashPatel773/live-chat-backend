FROM php:8.2-cli

WORKDIR /app

COPY . .

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    zip \
    default-mysql-client

RUN docker-php-ext-install zip pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install

EXPOSE 10000

CMD sh -c "php artisan config:clear && for i in 1 2 3 4 5; do php artisan migrate --force && break || sleep 5; done; php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"        