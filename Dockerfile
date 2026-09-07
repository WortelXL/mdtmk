FROM php:8.3-apache

# pdo_mysql is nodig voor de databaseverbinding (naar de gedeelde MKAPP-database)
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=10M'; \
        echo 'memory_limit=128M'; \
    } > /usr/local/etc/php/conf.d/mdt.ini

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
