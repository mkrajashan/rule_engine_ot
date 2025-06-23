FROM php:8.2-cli

# System packages
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHPUnit & PHPStan
RUN composer global require phpunit/phpunit phpstan/phpstan

# Add Composer binaries to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

# Optional: copy custom php.ini
COPY docker/php/php.ini /usr/local/etc/php/php.ini
