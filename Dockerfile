FROM php:8.4-apache

# Install required packages  at os level
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev sqlite3 \
    zip \
    unzip \
    libzip-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    libmagickwand-dev \
    libicu-dev \
    libonig-dev \
    ghostscript \
    ffmpeg

# Install required PHP extensions
RUN docker-php-ext-install zip intl

# Install webdav for player logs
RUN a2enmod dav dav_fs

# Install Imagick
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# workkdir in the container
WORKDIR /var/www

# copy only composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader
# see dockerignore
COPY . /var/www/
COPY dockerapp-configs/http.conf /etc/apache2/sites-available/000-default.conf
COPY ./dockerapp-configs/php.ini-custom /usr/local/etc/php/conf.d/docker-php-ext-custom.ini
COPY ./dockerapp-configs/policy.xml /etc/ImageMagick-6/policy.xml
COPY ./dockerapp-configs/env.edge /var/www/.env

RUN chown -R www-data:www-data /var/www/

RUN a2enmod rewrite # Enable mod_rewrite

EXPOSE 80

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
