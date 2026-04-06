FROM php:8.2-cli-bookworm

# Install system tools and MongoDB PHP extension required by the app.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libssl-dev pkg-config \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy application files.
COPY . .

# Install PHP dependencies for production.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Render provides PORT dynamically. Default is for local container runs.
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t public"]
