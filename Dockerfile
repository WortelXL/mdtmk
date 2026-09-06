FROM php:8.3-apache

# pdo_mysql is nodig voor de databaseverbinding (naar de gedeelde MKAPP-database)
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=10M'; \
        echo 'memory_limit=128M'; \
    } > /usr/local/etc/php/conf.d/mdt.ini

# Fase M4: foto's staan als gewone bestanden onder /uploads -- geen
# directory listing, zodat je niet zomaar alle geuploade foto's van
# alle meldingen kunt opsommen zonder de (niet-raadbare) bestandsnaam
# te kennen.
RUN { \
        echo '<Directory /var/www/html/uploads>'; \
        echo '    Options -Indexes'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/mdt-uploads.conf \
    && a2enconf mdt-uploads

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
