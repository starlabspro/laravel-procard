# Development image for running composer and the test suite against a
# specific PHP version. Defaults to 8.3, the minimum this package supports.
#
#   docker build -t laravel-procard:8.3 --build-arg PHP_VERSION=8.3 .
#   docker run --rm -v "$PWD":/app laravel-procard:8.3 composer update
#
# Build with PHP_VERSION=8.4 or 8.5 to check the rest of the matrix.
ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli

# git and unzip let composer use dist archives and VCS sources;
# libzip-dev backs the zip extension.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHPStan's parallel workers exceed the 128M default.
RUN echo 'memory_limit = -1' > /usr/local/etc/php/conf.d/zz-memory-limit.ini

# Match the host user so composer.lock and vendor/ are not written as root.
ARG UID=1000
ARG GID=1000
RUN groupadd -g "${GID}" app \
    && useradd -u "${UID}" -g "${GID}" -m -s /bin/bash app

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app
USER app

CMD ["composer", "--version"]
