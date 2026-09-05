FROM composer:2 AS composer_bin

FROM php:8.3-apache

# pdo_mysql is nodig voor de databaseverbinding (naar de gedeelde MKAPP-database);
# curl + mbstring zijn nodig voor de Web Push-library (fase M5).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev unzip \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

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

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

# Fase M5: Web Push versturen (verstuur_push_naar_gebruiker() in
# includes/functions.php) leunt op de beproefde, veelgebruikte
# minishlink/web-push-library (composer.json) i.p.v. zelfgebouwde
# cryptografie -- bij dit ene, foutgevoelige stukje (VAPID/encryptie) is
# dat bewust een uitzondering op de rest van dit project (dat verder
# bewust dependency-vrij is, zie includes/minipdf.php in de MKAPP-repo).
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
