FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libssl-dev \
    pkg-config \
    && docker-php-ext-install zip \
    && pecl install mongodb-1.21.1 \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

WORKDIR /var/www/html

RUN php -m | grep mongodb
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]